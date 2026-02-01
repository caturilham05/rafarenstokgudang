<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShopeeController;
use App\Http\Controllers\ShopeeWebhookController;
use App\Http\Controllers\TiktokController;
use App\Http\Controllers\TiktokWebhookController;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderReturn;
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
use App\Jobs\ProcessTiktokOrderWebhook;


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

    $stores = Store::where('id', 10)->get();

    $timeFrom = strtotime('2026-01-31 00:00:00');
    $timeTo   = strtotime('2026-02-01 23:59:59');

    $limit    = 50;
    $page     = 1;
    $maxPage  = 50;
    $delayUs  = 300000; // 0.3 detik

    foreach ($stores as $store) {

        if ($store->marketplace_name !== 'Tiktok') {
            continue;
        }

        $api = new TiktokApiService($store);

        Log::channel('tiktok')->info('Start sync TikTok order', [
            'shop_id' => $store->shop_id,
            'from'    => $timeFrom,
            'to'      => $timeTo,
        ]);

        while ($page <= $maxPage) {

            $response = $api->get(
                '/order/202309/orders/search',
                [
                    'shop_cipher' => $store->chiper,
                    'page'        => $page,
                    'page_size'   => $limit,
                    'create_time_ge' => $timeFrom,
                    'create_time_lt' => $timeTo,
                ],
                $store->access_token
            );

            if (!empty($response['code'])) {
                Log::channel('tiktok')->warning($response['message']);
                break;
            }

            $orders = $response['data']['orders'] ?? [];

            if (empty($orders)) {
                break;
            }

            foreach ($orders as $order) {

                $orderId = $order['id'] ?? null;
                $status  = $order['status'] ?? null;

                if (!$orderId || !$status) {
                    continue;
                }

                // ✅ skip kalau sudah ada
                if (Order::where('invoice', $orderId)->exists()) {
                    continue;
                }

                // 🔥 SIMULASI PAYLOAD WEBHOOK TIKTOK
                $payload = [
                    'shop_id' => $store->shop_id,
                    'data' => [
                        'order_id'     => $orderId,
                        'order_status' => $status,
                    ],
                ];

                dispatch(new ProcessTiktokOrderWebhook($payload))
                    ->onQueue('tiktok');

                usleep($delayUs);
            }

            if (count($orders) < $limit) {
                break;
            }

            $page++;
        }

        Log::channel('tiktok')->info('Finish sync TikTok order', [
            'shop_id' => $store->shop_id,
            'page'    => $page,
        ]);
    }



    // $store    = Store::where('id', 10)->get();
    // $timeFrom = strtotime('2026-01-31 00:00:00');
    // $timeTo   = strtotime('2026-02-01 23:59:59');
    // $api      = new TiktokApiService($store);


    // $store      = Store::get();
    // $timeFrom   = strtotime('2026-01-31 00:00:00');
    // $timeTo     = strtotime('2026-02-01 23:59:59');
    // $apiService = app(ShopeeApiService::class);

    // foreach ($store as $value) {
    //     switch ($value->marketplace_name) {
    //         case 'Shopee':
    //             $accessToken = $value->access_token;
    //             $shopId      = (int) $value->shop_id;

    //             $cursor = '';
    //             $hasNextPage = true;

    //             // 🔹 Ambil detail order
    //             $orderDetail = $apiService->getOrderDetail(
    //                 $accessToken,
    //                 $shopId,
    //                 '260201GW6B2TJ6'
    //             );

    //             // 🔹 Ambil escrow
    //             $escrowDetail = $apiService->getEscrowDetail(
    //                 $accessToken,
    //                 $shopId,
    //                 '260201GW6B2TJ6'
    //             );

    //             dd($orderDetail, $escrowDetail);


    //             while ($hasNextPage) {

    //                 $response = $apiService->getOrder(
    //                     $accessToken,
    //                     $shopId,
    //                     $timeFrom,
    //                     $timeTo,
    //                     50, // page size (max 50 Shopee)
    //                     'READY_TO_SHIP',
    //                     'create_time',
    //                     $cursor
    //                 );

    //                 if (empty($response['response']['order_list'])) {
    //                     break;
    //                 }

    //                 foreach ($response['response']['order_list'] as $order) {

    //                     $orderSn = $order['order_sn'];

    //                     // 🔹 Ambil detail order
    //                     $orderDetail = $apiService->getOrderDetail(
    //                         $accessToken,
    //                         $shopId,
    //                         $orderSn
    //                     );

    //                     // 🔹 Ambil escrow
    //                     $escrowDetail = $apiService->getEscrowDetail(
    //                         $accessToken,
    //                         $shopId,
    //                         $orderSn
    //                     );

    //                     /**
    //                      * TODO:
    //                      * - Simpan ke database
    //                      * - Mapping field sesuai kebutuhan
    //                      */
    //                 }

    //                 // 🔁 Pagination
    //                 $hasNextPage = $response['response']['more'] ?? false;
    //                 $cursor      = $response['response']['next_cursor'] ?? '';

    //             }

    //             break;

    //         case 'Tiktok':
    //             # code...
    //             break;

    //         default:
    //             # code...
    //             break;
    //     }
    // }

    // $store = Store::findOrFail(11);

    // $apiTiktok = new TiktokApiService($store);
    // $path      = "/product/202502/products/search";
    // $pageToken = '';

    // $query = [
    //     'shop_cipher' => $store->chiper,
    //     'version'     => '202502',
    //     'page_size'   => $pageSize ?? 100,
    //     'page_token'  => $pageToken,
    // ];

    // $body = [
    //     'status' => 'ACTIVATE',
    // ];

    // $response = $apiTiktok->post(
    //     $path,
    //     $query,
    //     $body,
    //     $store->access_token
    // );

    // dd($response);





    // return abort(404);
    // $order_product = OrderProduct::where('product_id', 0)->where('product_online_id', '!=', 0)->limit(2)->get();
    // foreach ($order_product as $value) {
    //     $product = Product::where('product_online_id', $value->product_online_id)->where('product_model_id', $value->product_model_id)->first();
    // }

    // OrderProduct::where('product_id', 0)
    // ->where('product_online_id', '!=', 0)
    // ->orderBy('id', 'desc')
    // ->chunk(100, function ($orderProducts) {
    //     foreach ($orderProducts as $value) {
    //         $product = Product::where('product_online_id', $value->product_online_id)
    //             ->where('product_model_id', $value->product_model_id)
    //             ->first();

    //         if ($product) {
    //             $value->product_id = $product->id;
    //             $value->save();
    //         }
    //     }
    // });
});
