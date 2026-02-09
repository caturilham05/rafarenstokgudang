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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

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
    return abort(404);
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

Route::get('/redis-test', function () {
    $redis = Redis::connection();
    dd($redis);
    // Cache::put('test', 'ok', 10);
    // return Cache::get('test');
});

Route::get('/redis-inspect', function () {

    // ambil key dengan SCAN (AMAN)
    $cursor = 0;
    $keys   = [];

    do {
        [$cursor, $result] = Redis::scan($cursor, [
            'match' => '*',
            'count' => 50,
        ]);

        $keys = array_merge($keys, $result);

    } while ($cursor != 0);

    return response()->json([
        'total_keys' => count($keys),
        'keys'       => $keys,
    ]);
});

Route::get('/redis-stats', function () {

    $info = Redis::command('info');

    return response()->json([
        'hits'   => (int) ($info['keyspace_hits'] ?? 0),
        'misses' => (int) ($info['keyspace_misses'] ?? 0),
        'ratio'  => isset($info['keyspace_hits'], $info['keyspace_misses'])
            ? round(
                $info['keyspace_hits'] /
                max(1, ($info['keyspace_hits'] + $info['keyspace_misses'])) * 100,
                2
              ) . '%'
            : null,
    ]);
});

Route::get('/redis-queue', function () {

    return [
        'pending_jobs' => Redis::llen('queues:default'),
        'failed_jobs'  => Redis::llen('queues:failed'),
    ];
});

