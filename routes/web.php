<?php

use Illuminate\Support\Facades\Route;
use Filament\Facades\Filament;
use App\Http\Controllers\ShopeeController;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\Store;
use App\Services\Shopee\ShopeeApiService;
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
    //     $store = Store::getStores(10)->first();
    //     dd($store);
    // return 'error';

    $order_sn = '251214B9XAB0ST'; //escrow_amount_after_adjustment awal 12749
    // $order_sn = '251215CQBAPFHQ'; //imas
    // $order_sn = '2512112GPRF6G8';
    // $order_sn = '251121C6WXBQ5D'; //sudah terbayar
    // $order_sn = '251214B9XAB0ST';
    // $order_sn = '251207PU5Q35GH'; //discount pembeli
    // $order_sn = '251205HNF8BYT8'; //discount pembeli
    $accessToken = 'eyJhbGciOiJIUzI1NiJ9.CN73ehABGILIoaABIAEok9r7yQYw_6qvuAM4AUAB.b7G8cSiV1RNtJultJmC0KhyMygED0crj6u7JqKTJfP4';
    $shopId = 336094210;

    DB::beginTransaction();
    try {

        $store = Store::getStores($shopId)->first();

        $apiService = app(ShopeeApiService::class);
        $response1 = $apiService->getOrderDetail($accessToken, $shopId, $order_sn);
        $response2 = $apiService->getEscrowDetail($accessToken, $shopId, $order_sn);
        dd($response1, $response2);

        // if (!empty($response['error'])) {
        //     return $response['error'];
        // }

        // $response = $apiService->getEscrowDetail($accessToken, $shopId, $order_sn);
        // dd($response);

        $order = $response['response']['order_list'][0] ?? [];

        // if (empty($order)) {
        //     return "Order not found";
        // }

        $recipient = $order['recipient_address'] ?? [];

        $order_proccessed = Order::updateOrCreate(
            [
                'invoice' => $order_sn,
            ],
            [
                'invoice' => $order_sn,
                'store_id' => $store ? $store->id : 0,
                'buyer_username' => $order['buyer_username'],
                'customer_name' => $recipient['name'],
                'customer_phone' => $recipient['phone'],
                'customer_address' => $recipient['full_address'],
                'courier' => $order['shipping_carrier'],
                'qty' => count($order['item_list'] ?? 0),
                'shipping_cost' => $order['estimated_shipping_fee'],
                'status' => $order['order_status'],
                'buyer_username' => $order['buyer_username'],
                'notes' => $order['message_to_seller'],
                'payment_method' => $order['payment_method'],
                'order_time' => date('Y-m-d H:i:s', $order['create_time'] ?? time()),
            ]
        );

        $orderId = $order_proccessed->id ?? 0;

        if (empty($orderId)) {
            throw new \Exception(sprintf("Failed to insert order %s", $order_sn));
        }

        foreach ($order['item_list'] as $item) {
            $productId = Product::getProducts(strval($item['item_id']), strval($item['model_id'] ?? null))->value('id');
            if (empty($productId)) {

            } else {
                $order_product_pre_insert = [
                    'order_id' => $orderId,
                    'product_id' => $productId,
                    'qty' => $item['model_quantity_purchased'],
                    'sale' => !empty($item['model_discounted_price']) ? $item['model_discounted_price'] : $item['model_original_price'],
                ];

                OrderProduct::updateOrCreate(
                    ['order_id' => $orderId, 'product_id' => $productId],
                    $order_product_pre_insert
                );
            }
        }

        DB::commit();
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Gagal sync order', [
            'invoice' => $order_sn,
            'error' => $e->getMessage()
        ]);
        return "error. ". $e->getMessage();
    }

    // $response = $apiService->getEscrowDetail($accessToken, $shopId, $order_sn);
    // $response = $apiService->getTrackingNumber($accessToken, $shopId, $order_sn);
    // dd($order_proccessed, $store, $response);
    dd($order);
});
