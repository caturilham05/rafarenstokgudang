<?php

use Illuminate\Support\Facades\Route;
use Filament\Facades\Filament;
use App\Http\Controllers\ShopeeController;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\Store;
use App\Services\Shopee\ShopeeApiService;
use App\Services\Shopee\ShopeeAuthService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// http://demo.rafarenstokgudang.com/shopee_redirect_auth_demo
// https://966946d32d4a.ngrok-free.app/shopee_redirect_auth_demo
Route::get('/shopee_redirect_auth_demo', [ShopeeController::class, 'shopee_redirect_auth_demo']);

// 6a41486b447075666b6b61665a586366
Route::get('/shopee/callback', [ShopeeController::class, 'callback'])->name('shopee.callback');
Route::get('/shopee/shop-info', [ShopeeController::class, 'shopeeShopInfo'])->name('shopee.shopinfo');
Route::get('/shopee/get-products', [ShopeeController::class, 'shopeeGetProducts'])->name('shopee.getproducts');
Route::get('/shopee/refresh-token', [ShopeeController::class, 'refreshToken'])->name('shopee.refreshtoken');

Route::get('/test', function(){
    try {
        // $order_sn   = '251217KTP7ASNW';
        // $order_sn   = '251217KUXYYX4P';
        // $order_sn   = '251217KWQADMQH'; // imas
        $order_sn   = '251218M0CD5XX0';

        $order      = Order::where('invoice', $order_sn)->first();
        $apiService = app(ShopeeApiService::class);
        $response   = $apiService->getOrderDetail('eyJhbGciOiJIUzI1NiJ9.CMb4ehABGLjl1-IBIAEo89CVygYwpYORmg04AUAB.XUhvC7X-LdIHfibbLDqprzGakuyDNFTU7V9ivTN5cK0', '475394744', $order_sn);
        // $response   = $apiService->getEscrowDetail('eyJhbGciOiJIUzI1NiJ9.CMb4ehABGLjl1-IBIAEo84qLygYwyNfCvwY4AUAB.u_uvQCPyRDSIB7N6hzoSJRniYPWFcYtK4trQAMRVczc', '475394744', $order_sn);
        dd($response);
        // $response   = $apiService->getEscrowDetail('eyJhbGciOiJIUzI1NiJ9.CN73ehABGILIoaABIAEo1K-FygYwk-Oosw04AUAB.T-LOAE5hzryheCwxYCXMO9shdkazIC0Z0glET4SKQOg', '336094210', $order_sn);
        // $auth = app(ShopeeAuthService::class);
        // $controller = app(ShopeeController::class);
        // $response = $controller->refreshToken(226246138, 'eyJhbGciOiJIUzI1NiJ9.CN73ehABGILIoaABIAIo1K-FygYwj_q97gU4AUAB.1Wdlz6zmCru2FKTVZu5DzWvNZzezNE-AqM7Atjk4zj0', $auth);
        // dd($response);

    } catch (\Throwable $th) {
        return preg_replace('/\[[^\]]*\]/', ' ', $th->getMessage());
    }
});
