<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use Filament\Notifications\Notification;
use App\Models\Order;
use App\Models\Packer;
use App\Models\Product;
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
    protected static ?int $navigationSort                        = 2;
    protected string $view                                       = 'filament.pages.order-scan';

    public ?string $barcode     = null;
    public ?Order $scannedOrder = null;

    protected function getFormSchema(): array
    {
        return [
            TextInput::make('barcode')
                ->label('Scan Waybill')
                ->placeholder('Scan barcode waybill here...')
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
                throw new \Exception('barcode cannot empty');
            }

            $barcode_explode = explode(',', $this->barcode);
            $barcode         = $barcode_explode[0] ?? null;
            $packer          = $barcode_explode[1] ?? null;

            if (empty($packer)) {
                throw new \Exception('invalid barcode, Please fill in the barcode such as [waybill,packer_name]. (use comma)');
            }

            $packer = Packer::where('packer_name', $packer)->first();
            $order  = Order::where('waybill', $barcode)->first();

            if (!$order) {
                throw new \Exception(sprintf('[%s] not found in sistem', $barcode));
            }

            if ($order->status !== 'PROCESSED') {
                throw new \Exception(sprintf('waybill [%s] can be scanned if it has [PROCESSED] status', $barcode));
            }

            // if (!empty($order->packer_id) || !empty($order->packer_name)) {
            //     throw new \Exception(sprintf('waybill [%s] has been scanned previously', $barcode));
            // }

            $orderProducts = $order->orderProducts;
            foreach ($orderProducts as $item) {
                $product = Product::where('id', $item->product_id)
                ->lockForUpdate()
                ->first();

                if (!$product) {
                    continue;
                }

                // decrement stock
                $affected = Product::where('id', $product->id)
                    ->where('stock', '>', 0)
                    ->decrement('stock', $item->qty);

                if ($affected === 0) {
                    throw new \Exception("Stok tidak cukup untuk produk ID {$product->id}");
                }

            }

            $order->update([
                'packer_id'   => $packer->id,
                'packer_name' => $packer->packer_name,
                // 'status' => 'SCANNED'
            ]);

            $this->scannedOrder = $order->fresh('orderProducts.product');

            Notification::make()
                ->title('Scan berhasil')
                ->success()
                ->body(sprintf('waybill [%s] from invoice [%s] scanned succesfully', $barcode, $order->invoice))
                ->send();

            DB::commit();
            $this->reset('barcode');
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
