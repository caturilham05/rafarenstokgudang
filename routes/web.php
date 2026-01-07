<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShopeeController;
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
    try {
        $return_id = '4038116819103286293';
        $shop_id   = '7494083405584303713';
        $store     = Store::where('shop_id', $shop_id)->first();
        if (!$store) {
            throw new \Exception('toko tidak ditemukan');
        }

        $api = new TiktokApiService($store);
        $query = [
            'shop_cipher' => $store->chiper,
        ];

        $body = [
            'return_ids' => [$return_id]
        ];

        $response = $api->post('/return_refund/202309/returns/search', $query, $body, $store->access_token);
        if (!empty($response['code'])) {
            throw new \Exception($response['message']);
        }

        $response_record = $api->get("/return_refund/202309/returns/{$return_id}/records", $query, $store->access_token);
        if (!empty($response_record['code'])) {
            throw new \Exception($response_record['message']);
        }

        $return_order         = $response['data']['return_orders'][0];
        $response_record_data = $response_record['data']['records'][0];
        $invoice              = $return_order['order_id'];
        $order                = Order::where('invoice', $invoice)->first();

        $orderReturn = [
            'order_id'       => $order->id ?? 0,
            'invoice_order'  => $return_order['order_id'] ?? NULL,
            'invoice_return' => $return_order['return_id'],
            'waybill'        => $return_order['return_tracking_number'] ?? NULL,
            'courier'        => $return_order['return_provider_name'] ?? NULL,
            'reason'         => !empty($return_order['return_reason']) && !empty($return_order['return_reason_text']) ? sprintf('%s - %s', $return_order['return_reason'], $return_order['return_reason_text']) : NULL,
            'reason_text'    => $response_record_data['note'] ?? NULL,
            'refund_amount'  => $return_order['refund_amount']['refund_total'] ?? 0,
            'return_time'    => !empty($return_order['create_time']) ? date('Y-m-d H:i:s', $return_order['create_time']) : NULL,
            'status'         => $return_order['return_status'] ?? NULL,
        ];

        $order_return = OrderReturn::updateOrCreate(
            [
                'invoice_return' => $return_order['return_id']
            ],
            $orderReturn
        );


        dd($order ?? [], $order_return, $orderReturn, $response, $response_record);


        // $return_sn = '2601070DXGYY0FB';
        // $query     = request()->all();
        // $shopId    = '475394744';
        // $store     = Store::getStores($shopId)->first();
        // if (is_null($store)) {
        //     Log::channel('shopee')->info('Toko tidak ditemukan');
        //     return response()->json(['status' => 'Toko tidak ditemukan']);
        // }

        // $accessToken = $store->access_token;
        // $apiService  = app(ShopeeApiService::class);
        // $response    = $apiService->getReturnDetail($accessToken, $shopId, $return_sn);
        // if (!empty($response['error'])) {
        //     throw new \Exception($response['message']);
        // }

        // $invoice_return = $response['response']['return_sn'] ?? NULL;
        // if (empty($invoice_return)) {
        //     throw new \Exception('invoice return tidak ditemukan');
        // }

        // $order_sn = $response['response']['order_sn'] ?? NULL;
        // if (empty($order_sn)) {
        //     throw new \Exception('order sn tidak ditemukan');
        // }
        // $order       = Order::where('invoice', $order_sn)->first();
        // $orderReturn = [
        //         'order_id'        => $order->id ?? 0,
        //         'invoice_order'   => $order_sn,
        //         'invoice_return'  => $invoice_return ?? NULL,
        //         'waybill'         => $response['response']['tracking_number'] ?? NULL,
        //         'buyer_username'  => $response['response']['user']['username'] ?? NULL,
        //         'courier'         => $response['response']['reverse_logistics_channel_name'] ?? NULL,
        //         'reason'          => $response['response']['reason'] ?? NULL,
        //         'reason_text'     => $response['response']['text_reason'] ?? NULL,
        //         'refund_amount'   => $response['response']['refund_amount'] ?? NULL,
        //         'return_time'     => !empty($response['response']['create_time']) ? date('Y-m-d H:i:s', $response['response']['create_time']) : NULL,
        //         'status'          => $response['response']['status'] ?? NULL,
        //         'status_logistic' => $response['response']['logistics_status'] ?? NULL,
        // ];

        // $order_return = OrderReturn::updateOrCreate(
        //     [
        //         'invoice_return' => $invoice_return
        //     ],
        //     $orderReturn
        // );

        // dd($order, $order_return ?? [], $orderReturn, $response);
    } catch (\Throwable $th) {
        dd($th->getMessage());
    }
});
