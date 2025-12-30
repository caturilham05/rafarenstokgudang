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

    $order_id = '581886046101735223';
    $status   = 'AWAITING_SHIPMENT';
    $shop_id  = '7494449492251347268';

    $invoices = [
        '581887057554343231',
        '581886046101735223',
        '581886876901868679',
        '581886865487333012',
        '581883963389936685',
        '581885773908248120',
        '581878059744527900',
        '581874565661886019',
        '581884584378271517',
        '581880703009195170',
        '581887396592714944',
        '581887295546557631',
        '581882137257805054',
        '581886659521840811',
        '581887450324436590',
        '581887303582648163',
        '581884932015556089',
        '581887144425064106',
        '581887248565438340',
        '581884116129580798',
        '581887330353055427',
        '581876277682734446',
        '581886840396023302',
        '581887332770612343',
        '581877963764696798',
        '581883303113492142',
        '581887288398808873',
    ];

    $invoices_implode = implode(',', $invoices);
    dd($invoices_implode);

    // dd(implode(',' $invoices));

    $invoices = ['581837043830064223'];

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










    $data             = request()->all();
    $start_date       = $data['start_date'];
    $marketplace_name = $data['marketplace_name'];
    $store_name       = $data['store_name'];
    $page             = $data['page'] ?? 0;
    $next             = $data['next_page_token'] ?? '';
    $per_page         = $data['per_page'] ?? 10;
    $url              = url()->current();

    if (!empty($page) && empty($next)) {
        return response()->json([
            'message' => 'sync order marketplace done'
        ]);
    }

    $store = Store::where('marketplace_name', $marketplace_name)->where('store_name', $store_name)->first();
    if (empty($store)) {
        return response()->json(['message' => sprintf('toko %s - %s tidak terdaftar disistem', $marketplace_name, $store_name)]);
    }

    if (preg_match('/tiktok/', $marketplace_name))
    {
        $api   = new TiktokApiService($store);
        $query = [
            'shop_cipher' => $store->chiper,
            'page_size'   => $per_page,
        ];

        if (!empty($next)) {
            $query['page_token'] = $next;
        }

        $body = [
            'order_status'   => 'AWAITING_COLLECTION',
            'create_time_ge' => strtotime($start_date)
        ];

        $response = $api->post('/order/202309/orders/search', $query, $body ?? $body, $store->access_token);
        if (!empty($response['code'])) {
            return response()->json([
                'message' => $response['message'],
                'code'    => $response['code']
            ]);
        }

        $next_page_token = $response['data']['next_page_token'] ?? '';

        foreach ($response['data']['orders'] as $ro)
        {
            $order = Order::where('invoice', $ro['id'])->first();
            if (!empty($order))
            {
                if (empty($order->waybill))
                {
                    $order_exists[] = [
                        'invoice'            => $order->invoice,
                        'waybill'            => $ro['tracking_number'],
                        'status_marketplace' => $ro['status'],
                        'date'               => date('Y-m-d H:i:s', $ro['create_time'])
                    ];
                    $order->update([
                        'waybill' => $ro['tracking_number'],
                        'status'  => $ro['status']
                    ]);
                } else {
                    $order_exists[] = [
                        'date' => date('Y-m-d H:i:s', $ro['create_time'])
                    ];
                }
            }
        }

        $last_data = end($order_exists) ?? '';

        if (date('Y-m-d', strtotime($last_data['date'])) !== date('Y-m-d', strtotime($start_date))) {
            return response()->json([
                'message' => 'tanggal tidak sesuai: start '.date('Y-m-d', strtotime($start_date)).', Now : '.date('Y-m-d')
            ]);
        }

        print_r($order_exists ?? []);
        print_r(end($order_exists) ?? []);
    }

    if (preg_match('/shopee/', $marketplace_name))
    {
        $timeTo              = Carbon::now()->timestamp;
        $timeFrom            = Carbon::now()->subDay()->timestamp;
        $api                 = app(ShopeeApiService::class);
        $response_order_list = $api->getOrder($store->access_token, $store->shop_id, $timeFrom, $timeTo, $per_page, 'PROCESSED', 'create_time', $next);

        if (!empty($response_order_list['error'])) {
            return response()->json(['message' => $response_order_list['message']]);
        }

        if (!$response_order_list['response']['more']) {
            return response()->json(['message' => 'sync shopee done']);
        }

        $next_page_token = $response_order_list['response']['next_cursor'] ?? '';

        $order_sn_arr = array_column($response_order_list['response']['order_list'], 'order_sn');
        foreach ($order_sn_arr as $order_sn) {
            $order = Order::where('invoice', $order_sn)->first();
            if (!empty($order))
            {
                print_r([
                    $order->waybill,
                    $order->order_time
                ]);

                if (empty($order->waybill))
                {
                    $response_tracking_number = $api->getTrackingNumber($store->access_token, $store->shop_id, $order_sn);
                    if (!empty($response_tracking_number['response']['tracking_number'])) {
                        // $order->update()
                    }
                }
            }
        }

        // echo "<pre>";
        // print_r($data_tracking_number ?? []);
        // echo "</pre>";
    }

    return response(
        '<meta http-equiv="refresh" content="5;url='.$url.'?per_page='.$per_page.'&start_date='.$start_date.'&marketplace_name='.$marketplace_name.'&store_name='.$store_name.'&page='.($page + 1).'&next_page_token='.$next_page_token.'">'
    );




    // $data = request()->all();
    // $order_sn = $data['invoice'];

    // $order_exists = Order::where('invoice', $order_sn)->first();
    // if (is_null($order_exists)) {
    //     throw new \Exception(sprintf('order %s tidak ditemukan', $order_sn));
    // }

    // $store = Store::findOrFail($order_exists->store_id);
    // $api   = new TiktokApiService($store);
    // $query = [
    //     'shop_cipher' => $store->chiper,
    //     'ids'         => $order_sn
    // ];

    // $response = $api->get('/order/202309/orders', $query, $store->access_token);
    // if (!empty($response['code'])) {
    //     return response()->json(['message' => $response['message']]);
    // }

    // unset($data);
    // $data = [
    //     'shop_id' => $store->shop_id,
    //     'data'    => [
    //         'order_id'     => $order_sn,
    //         'order_status' => $response['data']['orders'][0]['status'],
    //     ],
    // ];

    // return response()->json($data);

    // return app(TiktokWebhookController::class)->handleOrderDetail($data);
});
