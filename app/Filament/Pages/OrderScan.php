<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use Filament\Notifications\Notification;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Packer;
use App\Models\Product;
use App\Models\ProductMaster;
use App\Models\ProductMasterItem;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\QueryException;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Facades\DB;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
// use Illuminate\Support\Collection;
use Livewire\WithPagination;

// 375 = TO_CONFIRM_RECEIVE
// 1482 = SHIPPED

class OrderScan extends Page implements HasForms
{
    use Forms\Concerns\InteractsWithForms, HasPageShield, WithPagination;

    protected static ?string $navigationLabel                    = 'Order Scan';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-qr-code';
    protected static string | \UnitEnum | null $navigationGroup  = 'Order';
    protected static ?int $navigationSort                        = 3;
    protected string $view                                       = 'filament.pages.order-scan';

    public ?string $barcode     = null;
    public ?int $packer_id      = null;
    public bool $isScanning     = false;

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('packer_id')
                ->label('Packer Name')
                ->options(fn () => cache()->remember(
                    'packer_options',
                    3600,
                    fn () => Packer::pluck('packer_name', 'id')->toArray()
                ))
                ->searchable()
                ->reactive()
                ->required(),

            TextInput::make('barcode')
                ->label('Scan Waybill')
                ->placeholder('Scan barcode waybill here...')
                ->helperText('Please fill in the barcode such as [waybill]')
                ->autofocus()
                ->required()
                ->disabled(fn () => $this->isScanning)
                ->extraAttributes([
                    // barcode gun friendly
                    // 'wire:model.defer'   => 'barcode',

                    // hanya submit saat ENTER
                    'wire:keydown.enter' => 'submitScan',

                    // auto lock selama proses
                    'wire:loading.attr'  => 'disabled',
                    'wire:target'        => 'submitScan',
                ])
        ];
    }

    public function submitScan(): void
    {
        if ($this->isScanning) {
            return;
        }

        $this->isScanning = true;

        DB::beginTransaction();

        try {
            if (empty($this->packer_id)) {
                throw new \Exception('Please select packer first');
            }

            if (empty($this->barcode)) {
                throw new \Exception('barcode cannot be empty');
            }

            // ===============================
            // LOCK ORDER
            // ===============================
            $order = Order::query()
                ->select([
                    'id',
                    'waybill',
                    'invoice',
                    'status',
                    'packer_id',
                    'packer_name',
                    'scanned_at'
                ])
                ->where('waybill', $this->barcode)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                throw new \Exception("waybill [{$this->barcode}] not found in system");
            }

            if (!in_array($order->status, ['PROCESSED', 'AWAITING_COLLECTION'])) {
                throw new \Exception(
                    "waybill [{$order->waybill}] cannot be scanned, current status is [{$order->status}]"
                );
            }

            if (!empty($order->packer_id)) {
                throw new \Exception(
                    "waybill [{$order->waybill}] already assigned to packer [{$order->packer_name}]"
                );
            }

            // ===============================
            // AMBIL ORDER PRODUCTS
            // ===============================
            $orderProducts = OrderProduct::query()
                ->select('product_id', 'qty')
                ->where('order_id', $order->id)
                ->get();


            $productIds = $orderProducts->pluck('product_id')->unique()->values();

            // ===============================
            // VALIDASI PRODUCT MASTER EXIST
            // ===============================
            $registered = ProductMasterItem::whereIn('product_id', $productIds)
                ->pluck('product_id')
                ->unique();

            $diff = $productIds->diff($registered);

            if ($diff->isNotEmpty()) {

                $names = Product::whereIn('id', $diff)
                    ->pluck('product_name')
                    ->implode(', ');

                throw new \Exception(
                    "The following products are not yet registered in Product Master: {$names}"
                );
            }

            // ===============================
            // HITUNG REDUCTION VIA SQL
            // ===============================
            $masterReductions = DB::table('order_products as op')
                ->join('product_master_items as pmi', 'pmi.product_id', '=', 'op.product_id')
                ->select(
                    'pmi.product_master_id',
                    DB::raw('SUM(op.qty * pmi.stock_conversion) as total_reduce')
                )
                ->where('op.order_id', $order->id)
                ->groupBy('pmi.product_master_id')
                ->lockForUpdate()
                ->get();

            // ===============================
            // LOCK MASTER
            // ===============================
            $masters = ProductMaster::query()
                ->select('id', 'stock', 'product_name')
                ->whereIn('id', $masterReductions->pluck('product_master_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($masterReductions as $row) {

                $master = $masters[$row->product_master_id];

                if ($master->stock < $row->total_reduce) {
                    throw new \Exception(
                        "Insufficient stock of Product Master [{$master->product_name}]"
                    );
                }

                ProductMaster::where('id', $master->id)
                    ->where('stock', '>=', $row->total_reduce)
                    ->decrement('stock', $row->total_reduce);
            }

            // ===============================
            // DECREMENT PRODUCT STOCK
            // ===============================
            foreach ($orderProducts as $item) {

                $affected = Product::where('id', $item->product_id)
                    ->where('stock', '>=', $item->qty)
                    ->decrement('stock', $item->qty);

                if ($affected === 0) {

                    $name = Product::where('id', $item->product_id)
                        ->value('product_name');

                    throw new \Exception(
                        "Stock not sufficient for product {$name}"
                    );
                }
            }

            // ===============================
            // UPDATE ORDER
            // ===============================
            $packer = Packer::select('id', 'packer_name')->findOrFail($this->packer_id);


            $order->update([
                'packer_id'   => $packer->id,
                'packer_name' => $packer->packer_name,
                'status'      => 'SCANNED',
                'scanned_at'  => now()
            ]);

            // ===============================
            // PUSH TO SESSION LIST
            // ===============================
            if (collect($this->scannedOrders)->contains('id', $order->id)) {
                throw new \Exception("waybill already scanned in this session");
            }

            $this->scannedOrders->push($order);

            Notification::make()
                ->title('Scan Success')
                ->success()
                ->body(
                    "waybill [{$order->waybill}] from invoice [{$order->invoice}] scanned successfully"
                )
                ->send();

            DB::commit();
        } catch (\Throwable $th) {

            DB::rollBack();

            Notification::make()
                ->title($th->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->reset('barcode');
            $this->isScanning = false;
        }
    }

    public function getScannedOrdersProperty()
    {
        if (!$this->packer_id) {
            return collect();
        }

        return Order::query()
            ->select('id','waybill','packer_name','scanned_at')
            ->where('status','SCANNED')
            ->where('packer_id',$this->packer_id)
            ->whereDate('scanned_at', now())
            ->orderByDesc('scanned_at')
            ->paginate(20);
    }
}
