<?php

use Illuminate\Support\Facades\Route;
use Filament\Facades\Filament;
use App\Http\Controllers\ShopeeController;

// Route::get('/', function () {
//     return view('home');
// });

// Route::redirect('/', '/admin/login');

// Route::get('/', function () {
//     return redirect(Filament::getPanel('admin')->getLoginUrl());
// });

// Route::get('/shopee_redirect_auth_demo', function() {
//     $path = "/api/v2/shop/auth_partner";
//     $redirectUrl = "http://demo.rafarenstokgudang.com/";
//     $timest = time();
//     $baseString = sprintf("%s%s%s", env('SHOPEE_PARTNER_ID_TEST'), $path, $timest);
//     $sign = hash_hmac('sha256', $baseString, env('SHOPEE_PARTNER_KEY_TEST'));
//     $url = sprintf("%s%s?timestamp=%s&partner_id=%s&sign=%s&redirect=%s", env('SHOPEE_HOST'), $path, $timest, env('SHOPEE_PARTNER_ID_TEST'), $sign, $redirectUrl);
//     return $url;
// });

// http://demo.rafarenstokgudang.com/shopee_redirect_auth_demo
// https://966946d32d4a.ngrok-free.app/shopee_redirect_auth_demo
Route::get('/shopee_redirect_auth_demo', [ShopeeController::class, 'shopee_redirect_auth_demo']);

// 6a41486b447075666b6b61665a586366
Route::get('/shopee/callback', [ShopeeController::class, 'callback'])->name('shopee.callback');
Route::get('/shopee/shop-info', [ShopeeController::class, 'shopeeShopInfo'])->name('shopee.shopinfo');
Route::get('/shopee/get-products', [ShopeeController::class, 'shopeeGetProducts'])->name('shopee.getproducts');
Route::get('/shopee/refresh-token', [ShopeeController::class, 'refreshToken'])->name('shopee.refreshtoken');

Route::get('/test', function(){
    $refresh_token = '46475142444464444944566747634962';
    $acces_token = '6161624678487a474f4e52767170444c';
    $expire_in = 14399;
    $now = date('Y-m-d H:i:s', time());
    $expire_in_datetime = date('Y-m-d H:i:s', time() + $expire_in);
    return response()->json([
        'refresh_token' => $refresh_token,
        'access_token' => $acces_token,
        'expire_in' => $expire_in,
        'expire_in_datetime' => $expire_in_datetime,
        'now' => $now
    ]);

});
