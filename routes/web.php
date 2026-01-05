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
    $order_sn  = '251111FGVSJ3PU';
    $return_sn = '251201073WGXWT9';

    $query      = request()->all();
    $start_date = $query['start_date'] ?? null;
    $end_date   = $query['end_date'] ?? null;
    $shopId     = '475394744';
    $store      = Store::getStores($shopId)->first();
    if (is_null($store)) {
        Log::channel('shopee')->info('Toko tidak ditemukan');
        return response()->json(['status' => 'Toko tidak ditemukan']);
    }

    $accessToken = $store->access_token;
    $apiService  = app(ShopeeApiService::class);
    // $response    = $apiService->getReturn($accessToken, $shopId, 0, 10, strtotime($start_date), strtotime($end_date));
    $response = $apiService->getReturnDetail($accessToken, $shopId, $return_sn);
    dd($response);
});
