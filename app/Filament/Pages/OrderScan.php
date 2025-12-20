<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use Filament\Notifications\Notification;
use App\Models\Order;
use App\Models\Packer;
use App\Models\Product;
use App\Models\ProductMaster;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\QueryException;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Facades\DB;

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
    public ?Order $scannedOrder = null;

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('packer_id')
                ->label('Packer Name')
                ->options(
                    Packer::query()->pluck('packer_name', 'id')
                )
                ->searchable()
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
                    'waybill [%s] can be scanned only if status is [PROCESSED]',
                    $this->barcode
                ));
            }

            if ($order->packer_id || $order->packer_name) {
                throw new \Exception(sprintf(
                    'waybill [%s] has been scanned previously',
                    $this->barcode
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
                // 'status' => 'SCANNED',
            ]);

            $this->scannedOrder = $order->fresh('orderProducts.product');

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
}
