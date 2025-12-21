<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use Filament\Notifications\Notification;
use App\Models\Order;
use App\Models\Packer;
use App\Models\Product;
use App\Models\ProductMaster;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\QueryException;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Facades\DB;

// 375 = TO_CONFIRM_RECEIVE
// 1482 = SHIPPED

class OrderScan extends Page implements HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationLabel                    = 'Order Scan';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-qr-code';
    protected static string | \UnitEnum | null $navigationGroup  = 'Order';
    protected static ?int $navigationSort                        = 3;
    protected string $view                                       = 'filament.pages.order-scan';

    public ?string $barcode     = null;
    public ?int $packer_id      = null;
    public array $scannedOrders = [];

    public function updatedPackerId($value): void
    {
        if (empty($value)) {
            $this->scannedOrders = [];
            return;
        }

        $this->scannedOrders = Order::with('orderProducts.product')
            ->where('status', 'SCANNING')
            ->where('packer_id', $value)
            ->orderBy('updated_at')
            ->get()
            ->all();
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('packer_id')
                ->label('Packer Name')
                ->options(
                    Packer::query()->pluck('packer_name', 'id')
                )
                ->searchable()
                ->reactive()
                ->required(),

            TextInput::make('barcode')
                ->label('Scan Waybill')
                ->placeholder('Scan barcode waybill here...')
                ->helperText('Please fill in the barcode such as [waybill]')
                ->autofocus()
                ->required()
                ->reactive()
                ->extraAttributes([
                    'wire:keydown.enter' => 'submitScan'
                ]),
        ];
    }

    public function submitScan(): void
    {
        DB::beginTransaction();
        try {
            if (empty($this->barcode)) {
                throw new \Exception('barcode cannot be empty');
            }

            $order = Order::with('orderProducts')
                ->where('waybill', $this->barcode)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                throw new \Exception(sprintf('waybill [%s] not found in system', $this->barcode));
            }

            if ($order->status !== 'PROCESSED') {
                throw new \Exception(sprintf(
                    'waybill [%s] cannot be scanned, current status is [%s]',
                    $order->waybill,
                    $order->status
                ));
            }

            if (!empty($order->packer_id)) {
                throw new \Exception(sprintf(
                    'waybill [%s] already assigned to packer [%s]',
                    $order->waybill, $order->packer_name
                ));
            }

            // ===============================
            // AMBIL SEMUA PRODUCT ID SEKALI
            // ===============================
            $productIds = $order->orderProducts
                ->pluck('product_id')
                ->unique()
                ->values();

            // ===============================
            // LOCK SEMUA PRODUCT MASTER
            // ===============================
            $productMasters = ProductMaster::whereIn('product_id', $productIds)
                ->lockForUpdate()
                ->get()
                ->groupBy('product_id');

            // ===============================
            // VALIDASI PRODUCT MASTER ADA
            // ===============================
            foreach ($order->orderProducts as $item) {
                if (!isset($productMasters[$item->product_id])) {
                    throw new \Exception(sprintf(
                        'product [%s] does not have product master, please add it first',
                        $item->product_name
                    ));
                }

                $product = Product::where('id', $item->product_id)
                    ->lockForUpdate()
                    ->first();

                if ($product->stock < $item->qty) {
                    throw new \Exception("Stock not sufficient for {$item->product_name}");
                }

                $affected = Product::where('id', $item->product_id)
                    ->where('stock', '>=', $item->qty)
                    ->decrement('stock', $item->qty);

                if ($affected === 0) {
                    throw new \Exception(
                        "Stock not sufficient for product {$item->product_name}"
                    );
                }
            }

            // ===============================
            // DECREMENT STOCK (1 QUERY PER PRODUCT)
            // ===============================
            foreach ($productMasters as $productId => $masters) {
                $affected = ProductMaster::where('product_id', $productId)
                    ->whereColumn('stock', '>=', 'stock_conversion')
                    ->update([
                        'stock' => DB::raw('stock - stock_conversion'),
                    ]);

                if ($affected === 0) {
                    throw new \Exception(
                        "Stock not sufficient for product_id {$productId}"
                    );
                }
            }

            // ===============================
            // UPDATE ORDER
            // ===============================
            $packer = Packer::where('id', $this->packer_id)->first();
            $order->update([
                'packer_id'   => $packer->id,
                'packer_name' => $packer->packer_name,
                'status'      => 'SCANNING',
            ]);

            $order = $order->fresh('orderProducts.product');

            // ===============================
            // SIMPAN KE LIST SCAN (APPEND)
            // ===============================
            if (collect($this->scannedOrders)->contains('id', $order->id)) {
                throw new \Exception("waybill already scanned in this session");
            }

            $this->scannedOrders[] = $order;

            Notification::make()
                ->title('Scan berhasil')
                ->success()
                ->body(sprintf(
                    'waybill [%s] from invoice [%s] scanned successfully',
                    $this->barcode,
                    $order->invoice
                ))
                ->send();

            $this->reset('barcode');
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            if ($th instanceof QueryException) {
                Notification::make()
                    ->title('Internal Server Error')
                    ->danger()
                    ->send();

                $this->reset('barcode');
                return;
            }
            Notification::make()
                ->title($th->getMessage())
                ->danger()
                ->send();

            $this->reset('barcode');
        }
    }

    public function submitAll(): void
    {
        if (empty($this->scannedOrders)) {
            Notification::make()
                ->title('No order to submit')
                ->warning()
                ->send();
            return;
        }

        DB::beginTransaction();

        try {
            $orderIds = collect($this->scannedOrders)->pluck('id')->toArray();

            // lock semua order
            $orders = Order::whereIn('id', $orderIds)
                ->lockForUpdate()
                ->get();

            foreach ($orders as $order) {

                if ($order->status !== 'SCANNING') {
                    throw new \Exception(
                        "Order {$order->waybill} status invalid ({$order->status})"
                    );
                }

                $order->update([
                    'status' => 'SCANNED',
                ]);
            }

            DB::commit();

            Notification::make()
                ->title('Submit berhasil')
                ->success()
                ->body(count($orders) . ' order berhasil di-submit')
                ->send();

            // reset list
            $this->scannedOrders = [];

        } catch (\Throwable $th) {
            DB::rollBack();

            Notification::make()
                ->title($th->getMessage())
                ->danger()
                ->send();
        }
    }

    public function confirmSubmit()
    {
        Notification::make()
            ->title('Submit semua order?')
            ->body('Status akan diubah menjadi SCANNED')
            ->warning()
            ->actions([
                Action::make('submit')
                    ->label('Ya, Submit')
                    ->button()
                    ->action('submitAll'),
            ])
            ->send();
    }
}
