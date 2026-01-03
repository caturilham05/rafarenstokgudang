<?php

namespace App\Filament\Pages;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Packer;
use App\Models\Product;
use App\Models\Store;
use App\Services\Shopee\ShopeeApiService;
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

            if (preg_match('/shopee/i', $store->marketplace_name)) {
                $apiService  = app(ShopeeApiService::class);

                if ($order_exists) {
                    $tracking_number = $apiService->getTrackingNumber($store->access_token, $store->shop_id, $order_id);
                    $waybill         = $tracking_number['response']['tracking_number'] ?? '';

                    $order_exists->update([
                        'waybill' => $waybill,
                        'status'  => 'PROCESSED'
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

                $accessToken = $store->access_token;
                $shopId      = $store->shop_id;
                $order_sn    = $order_id;

                $response = $apiService->getOrderDetail($accessToken, $shopId, $order_sn);
                $order    = $response['response']['order_list'][0] ?? [];

                if (empty($order)) {
                    throw new \Exception(sprintf('order %s not found', $order_sn));
                }

                $tracking_number = $apiService->getTrackingNumber($accessToken, $shopId, $order_sn);
                $waybill         = $tracking_number['response']['tracking_number'] ?? '';

                $recipient = $order['recipient_address'] ?? [];

                $response_escrow = $apiService->getEscrowDetail($accessToken, $shopId, $order_sn);
                if (!empty($response_escrow['error'])) {
                    throw new \Exception('Error fetch escrow');
                }

                $order_income = $response_escrow['response']['order_income'] ?? [];
                if (empty($order_income)) {
                    throw new \Exception('order income belum tersedia');
                }

                $commission_fee                                = $order_income['commission_fee'] ?? 0;
                $delivery_seller_protection_fee_premium_amount = $order_income['delivery_seller_protection_fee_premium_amount'] ?? 0;
                $service_fee                                   = $order_income['service_fee'] ?? 0;
                $seller_order_processing_fee                   = $order_income['seller_order_processing_fee'] ?? 0;
                $voucher_from_seller                           = $order_income['voucher_from_seller'] ?? 0;

                $escrow_amount_after_adjustment = $order_income['escrow_amount_after_adjustment'] ?? 0;
                $total_price                    = !empty($escrow_amount_after_adjustment) ? $escrow_amount_after_adjustment : $response_escrow['response']['buyer_payment_info']['buyer_total_amount'];

                // $total_price_final = floor($total_price - ($total_price * 0.005));

                $qty_total = 0;
                foreach ($order['item_list'] as $value) {
                    $qty_total += $value['model_quantity_purchased'];
                }

                $orderModel = Order::updateOrCreate(
                    ['invoice' => $order_sn],
                    [
                        'invoice'                                       => $order_sn,
                        'store_id'                                      => $store->id,
                        'marketplace_name'                              => $store->marketplace_name,
                        'store_name'                                    => $store->store_name,
                        'buyer_username'                                => $order['buyer_username'],
                        'customer_name'                                 => $recipient['name'],
                        'customer_phone'                                => $recipient['phone'],
                        'customer_address'                              => $recipient['full_address'],
                        'courier'                                       => $order['package_list'][0]['shipping_carrier'],
                        'qty'                                           => $qty_total,
                        'shipping_cost'                                 => $order['estimated_shipping_fee'],
                        'status'                                        => $order['order_status'],
                        'notes'                                         => $order['message_to_seller'],
                        'payment_method'                                => $order['payment_method'],
                        'order_time'                                    => date('Y-m-d H:i:s', $order['create_time'] ?? time()),
                        'total_price'                                   => $total_price,
                        'commission_fee'                                => $commission_fee,
                        'delivery_seller_protection_fee_premium_amount' => $delivery_seller_protection_fee_premium_amount,
                        'service_fee'                                   => $service_fee,
                        'seller_order_processing_fee'                   => $seller_order_processing_fee,
                        'voucher_from_seller'                           => $voucher_from_seller,
                        'waybill'                                       => $waybill

                    ]
                );

                // cegah double stock
                if ($orderModel->wasRecentlyCreated === false) {
                    throw new \Exception('invalid action');
                }

                foreach ($order['item_list'] as $item) {

                    $qty = (int) $item['model_quantity_purchased'];

                    $product = Product::where('product_online_id', strval($item['item_id']))
                    ->where('product_model_id', strval($item['model_id']))
                    ->lockForUpdate()
                    ->first();

                    if (!$product) {
                        continue;
                    }

                    $order_product_pre_insert = [
                        'order_id'          => $orderModel->id,
                        'product_id'        => $product->id,
                        'product_online_id' => $product->product_online_id,
                        'product_model_id'  => $product->product_model_id,
                        'product_name'      => $product->product_name,
                        'varian'            => $product->varian,
                        'qty'               => $item['model_quantity_purchased'],
                        'sale'              => !empty($item['model_discounted_price']) ? $item['model_discounted_price'] : $item['model_original_price'],
                    ];

                    OrderProduct::updateOrCreate(
                        [
                            'order_id'   => $orderModel->id,
                            'product_id' => $product->id,
                        ],
                        $order_product_pre_insert
                    );
                }
            }

            if (preg_match('/tiktok/i', $store->marketplace_name)) {
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
