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
    try {
        // 251115RY239S8S
        // 251216F0NT6QJ0 origin
        $order_sn      = '251216F0NT6QJ0';
        $order         = Order::where('invoice', $order_sn)->first();
        $store         = Store::findOrFail($order->store_id);
        $api_service   = app(ShopeeApiService::class);
        $escrow_detail = $api_service->getEscrowDetail($store->access_token, $store->shop_id, $order_sn);

        if (!empty($escrow_detail['error'])) {
            throw new \Exception($escrow_detail['error']);
        }

        $order_income = $escrow_detail['response']['order_income'] ?? [];
        if (empty($order_income)) {
            throw new \Exception('order income belum tersedia');
        }

        $commission_fee                                = $order_income['commission_fee'];
        $delivery_seller_protection_fee_premium_amount = $order_income['delivery_seller_protection_fee_premium_amount'];
        $service_fee                                   = $order_income['service_fee'];
        $seller_order_processing_fee                   = $order_income['seller_order_processing_fee'];
        $voucher_from_seller                           = $order_income['voucher_from_seller'];
        // dd($commission_fee, $delivery_seller_protection_fee_premium_amount, $service_fee, $seller_order_processing_fee, $voucher_from_seller);

        $order->update([
            'commission_fee'                                => $commission_fee,
            'delivery_seller_protection_fee_premium_amount' => $delivery_seller_protection_fee_premium_amount,
            'service_fee'                                   => $service_fee,
            'seller_order_processing_fee'                   => $seller_order_processing_fee,
            'voucher_from_seller'                           => $voucher_from_seller
        ]);


        dd($order);

    } catch (\Throwable $th) {
        return preg_replace('/\[[^\]]*\]/', ' ', $th->getMessage());
    }
});
