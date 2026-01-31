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

    OrderProduct::where('product_id', 0)
    ->where('product_online_id', '!=', 0)
    ->orderBy('id', 'desc')
    ->chunk(100, function ($orderProducts) {
        foreach ($orderProducts as $value) {
            $product = Product::where('product_online_id', $value->product_online_id)
                ->where('product_model_id', $value->product_model_id)
                ->first();

            if ($product) {
                $value->product_id = $product->id;
                $value->save();
            }
        }
    });
});
