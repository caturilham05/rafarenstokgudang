<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShopeeController;
use App\Http\Controllers\TiktokController;
use App\Http\Controllers\TiktokWebhookController;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\ProductMaster;
use App\Models\ProductMasterItem;
use App\Models\Store;
use App\Services\Shopee\ShopeeApiService;
use App\Services\Tiktok\TiktokApiService;
use App\Services\Tiktok\TiktokAuthService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// http://demo.rafarenstokgudang.com/shopee_redirect_auth_demo
// https://966946d32d4a.ngrok-free.app/shopee_redirect_auth_demo
//shopee auth
Route::get('/shopee_redirect_auth_demo', [ShopeeController::class, 'shopee_redirect_auth_demo']);

//tiktok auth
Route::get('/tiktok/connect', [TiktokController::class, 'connect']);
Route::get('/tiktok/callback', [TiktokController::class, 'callback'])->name('tiktok.callback');


// 6a41486b447075666b6b61665a586366
Route::get('/shopee/callback', [ShopeeController::class, 'callback'])->name('shopee.callback');
Route::get('/shopee/shop-info', [ShopeeController::class, 'shopeeShopInfo'])->name('shopee.shopinfo');
Route::get('/shopee/get-products', [ShopeeController::class, 'shopeeGetProducts'])->name('shopee.getproducts');
Route::get('/shopee/refresh-token', [ShopeeController::class, 'refreshToken'])->name('shopee.refreshtoken');

Route::get('/test', function(){
    // return abort(404);

    // $order_id = '581886046101735223';
    $status   = 'AWAITING_SHIPMENT';
    $shop_id  = '7494449492251347268';

    // // $invoices = [
    // //     '581887057554343231',
    // //     '581886046101735223',
    // //     '581886876901868679',
    // //     '581886865487333012',
    // //     '581883963389936685',
    // //     '581885773908248120',
    // //     '581878059744527900',
    // //     '581874565661886019',
    // //     '581884584378271517',
    // //     '581880703009195170',
    // //     '581887396592714944',
    // //     '581887295546557631',
    // //     '581882137257805054',
    // //     '581886659521840811',
    // //     '581887450324436590',
    // //     '581887303582648163',
    // //     '581884932015556089',
    // //     '581887144425064106',
    // //     '581887248565438340',
    // //     '581884116129580798',
    // //     '581887330353055427',
    // //     '581876277682734446',
    // //     '581886840396023302',
    // //     '581887332770612343',
    // //     '581877963764696798',
    // //     '581883303113492142',
    // //     '581887288398808873',
    // // ];

    // $invoices_implode = implode(',', $invoices);

    $invoices = ['581930148380967998'];

    $invoice_exists = [];
    foreach ($invoices as $order_id)
    {
        $order_exists = Order::select('invoice')->where('invoice', $order_id)->first();

        if (is_null($order_exists))
        {
            $store = Store::where('shop_id', $shop_id)->first();
            if (is_null($store)) {
                throw new \Exception('toko tidak ditemukan');
            }

            $api = new TiktokApiService($store);
            $query = [
                'shop_cipher' => $store->chiper,
                'ids'         => $order_id
            ];

            $response = $api->get('/order/202309/orders', $query, $store->access_token);
            dd($response);
            if (!empty($response['code'])) {
                throw new \Exception($response['message']);
            }

            if (empty($response['data']['orders'][0])) {
                throw new \Exception('Order tidak ditemukan di TikTok API');
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
                throw new \Exception('Package ID tidak ditemukan');
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
                throw new \Exception('SKU package kosong');
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

                    dd($package);

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
                'marketplace_name' => $store->marketplace_name,
                'store_name'       => $store->store_name,
                'customer_name'    => $order['recipient_address']['name'] ?? null,
                'customer_phone'   => $order['recipient_address']['phone_number'] ?? null,
                'customer_address' => $order['recipient_address']['full_address'] ?? null,
                'courier'          => $order['shipping_provider'] ?? null,
                'qty'              => $quantity_total,
                'shipping_cost'    => $order['payment']['original_shipping_fee'] ?? 0,
                'total_price'      => $order['payment']['total_amount'] ?? 0,
                'status'           => $status,
                'notes'            => $order['buyer_message'] ?? null,
                'payment_method'   => $order['payment_method_name'] ?? null,
                'order_time'       => date('Y-m-d H:i:s', $order['create_time']),
            ];

            dd($order_pre_insert);

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
            sleep(1);
        } else {
            // $invoice_exists[] = $order_exists->invoice;
        }
    }

    echo "<pre>";
    print_r($packages_all ?? []);
    echo "</pre>";

    return;

    $order_sn = '2601033PT24Q6W';
    $shopId   = '475394744';

    $store = Store::getStores($shopId)->first();
    if (is_null($store)) {
        Log::channel('shopee')->info('Toko tidak ditemukan');
        return response()->json(['status' => 'Toko tidak ditemukan']);
    }

    $accessToken = $store->access_token;
    $apiService  = app(ShopeeApiService::class);

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



    dd($orderModel);



});
