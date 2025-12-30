<?php

namespace App\Filament\Pages;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Packer;
use App\Models\Product;
use App\Models\Store;
use App\Services\Tiktok\TiktokApiService;
use Filament\Pages\Page;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

class OrderCreate extends Page implements HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected string $view = 'filament.pages.order-create';
    protected static ?string $navigationLabel                    = 'Order Create or Update Waybill';
    protected static string | \BackedEnum | null $navigationIcon = Heroicon::PencilSquare;
    protected static string | \UnitEnum | null $navigationGroup  = 'Order';
    protected static ?int $navigationSort                        = 4;

    public ?string $invoice = null;
    public ?int $store_id   = null;


    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('store_id')
                ->label('Store Name')
                ->options(
                    Store::query()
                        ->get()
                        ->mapWithKeys(fn ($store) => [
                            $store->id => $store->store_name . ' - ' . $store->marketplace_name,
                        ])
                )
                ->searchable()
                ->reactive()
                ->required(),

            TextInput::make('invoice')
                ->label('Invoice Input')
                ->placeholder('Fill invoice order here...')
                ->autofocus()
                ->required()
                ->reactive()
                ->extraAttributes([
                    'wire:keydown.enter' => 'submitOrderCreate'
                ]),
        ];
    }

    public function submitOrderCreate(): void
    {
        DB::beginTransaction();
        try {
            $order_id     = $this->invoice;
            $store        = Store::findOrFail($this->store_id);
            $order_exists = Order::select('invoice')->where('invoice', $order_id)->first();
        // =========================================
        // JIKA ORDER SUDAH ADA → UPDATE WAYBILL
        // =========================================
            if ($order_exists) {

                if ($order_exists->status !== 'AWAITING_SHIPMENT') {
                    throw new \Exception(
                        "Order {$order_id} tidak bisa diupdate. Status sekarang: {$order_exists->status}"
                    );
                }

                $api      = new TiktokApiService($store);
                $response = $api->get(
                    '/order/202309/orders',
                    [
                        'shop_cipher' => $store->chiper,
                        'ids'         => $order_id,
                    ],
                    $store->access_token
                );

                if (!empty($response['code'])) {
                    throw new \Exception($response['message']);
                }

                $order = $response['data']['orders'][0] ?? null;
                if (!$order) {
                    throw new \Exception("Order {$order_id} tidak ditemukan di TikTok");
                }

                $order_exists->update([
                    'waybill' => $order['tracking_number'] ?? null,
                    'status'  => $order['status'] ?? null
                ]);

                DB::commit();

                Notification::make()
                    ->title('Waybill Updated')
                    ->success()
                    ->body("Waybill order [{$order_id}] berhasil diperbarui")
                    ->send();

                $this->reset(['invoice', 'store_id']);
                return;
            }


            $api   = new TiktokApiService($store);
            $query = [
                'shop_cipher' => $store->chiper,
                'ids'         => $order_id
            ];

            $response = $api->get('/order/202309/orders', $query, $store->access_token);
            if (!empty($response['code'])) {
                throw new \Exception($response['message']);
            }

            if (empty($response['data']['orders'][0])) {
                throw new \Exception(sprintf('invoice %s not found in API Tiktok', $order_id));
            }

            $order = $response['data']['orders'][0];

            /**
             * ===============================
             * MAP LINE ITEMS
             * ===============================
             */
            $order_products = [];
            foreach ($order['line_items'] as $op) {
                $total_price = max(0, $op['original_price'] - $op['seller_discount']);

                $order_products[$op['sku_id']] = [
                    'sku_id'            => $op['sku_id'],
                    'product_online_id' => $op['product_id'],
                    'product_name'      => $op['product_name'],
                    'total_price'       => $total_price,
                ];
            }

            if (empty($order['packages'][0]['id'])) {
                throw new \Exception('Package not found');
            }

            $package_id = $order['packages'][0]['id'];

            $response_package = $api->get(
                sprintf('/fulfillment/202309/packages/%s', $package_id),
                ['shop_cipher' => $store->chiper],
                $store->access_token
            );

            if (!empty($response_package['code'])) {
                throw new \Exception($response_package['message']);
            }

            $packages = $response_package['data']['orders'][0]['skus'] ?? [];
            if (empty($packages)) {
                throw new \Exception('package not found');
            }

            /**
             * ===============================
             * MAP PACKAGE → PRODUCT
             * ===============================
             */
            $quantity_total = 0;


            foreach ($packages as &$package) {

                $skuId = $package['sku_id'] ?? $package['id'];
                $productData = $order_products[$skuId] ?? null;

                if (!$productData) {
                    continue;
                }

                $package['product_online_id'] = $productData['product_online_id'];
                $package['product_model_id']  = $skuId;
                $package['product_name']      = $productData['product_name'];
                $package['sale']              = $productData['total_price'];
                $package['qty']               = $package['quantity'];

                $product = Product::where('product_online_id', $package['product_online_id'])
                    ->where('product_model_id', $package['product_model_id'])
                    ->first();

                if (!$product) {
                    continue;
                }

                $package['product_id'] = $product->id;
                $package['varian']     = $product->varian;

                $quantity_total += $package['qty'];

                unset(
                    $package['id'],
                    $package['sku_id'],
                    $package['image_url'],
                    $package['quantity']
                );
            }

            // $packages_all[] = $packages;

            /**
             * ===============================
             * INSERT / UPDATE ORDER
             * ===============================
             */
            $order_pre_insert = [
                'store_id'         => $store->id,
                'waybill'          => $order['tracking_number'] ?? NULL,
                'marketplace_name' => $store->marketplace_name,
                'store_name'       => $store->store_name,
                'customer_name'    => $order['recipient_address']['name'] ?? null,
                'customer_phone'   => $order['recipient_address']['phone_number'] ?? null,
                'customer_address' => $order['recipient_address']['full_address'] ?? null,
                'courier'          => $order['shipping_provider'] ?? null,
                'qty'              => $quantity_total,
                'shipping_cost'    => $order['payment']['original_shipping_fee'] ?? 0,
                'total_price'      => $order['payment']['total_amount'] ?? 0,
                'status'           => $order['status'],
                'notes'            => $order['buyer_message'] ?? null,
                'payment_method'   => $order['payment_method_name'] ?? null,
                'order_time'       => date('Y-m-d H:i:s', $order['create_time']),
            ];

            $order_insert = Order::updateOrCreate(
                ['invoice' => $order['id']],
                $order_pre_insert
            );

            foreach ($packages as $package_insert) {
                if (empty($package_insert['product_id'])) {
                    continue;
                }

                OrderProduct::updateOrCreate(
                    [
                        'order_id'   => $order_insert->id,
                        'product_id' => $package_insert['product_id'],
                    ],
                    $package_insert
                );
            }

            Notification::make()
                ->title('Create Order Success')
                ->success()
                ->body(
                    "Invoice order [{$order_id}] created successfully"
                )
                ->send();

            $this->reset('invoice');
            $this->reset('store_id');
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            Notification::make()
                ->title($th->getMessage())
                ->danger()
                ->send();

            $this->reset('invoice');
            $this->reset('store_id');
        }
    }
}
