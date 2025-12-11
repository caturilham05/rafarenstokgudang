<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Shopee\ShopeeAuthService;
use App\Services\Shopee\ShopeeSignature;
use App\Services\Shopee\ShopeeApiService;
use App\Models\Store;

class ShopeeController extends Controller
{
    protected $signature;
    protected $partnerId;
    protected $partnerKey;
    protected $host;
    public function __construct(ShopeeSignature $signature)
    {
        $this->signature = $signature;
        $this->partnerId  = env('SHOPEE_PARTNER_ID');
        $this->partnerKey = env('SHOPEE_PARTNER_KEY');
        $this->host       = env('SHOPEE_HOST');
    }

    public function shopee_redirect_auth_demo()
    {
        $path = "/api/v2/shop/auth_partner";
        $redirectUrl = route('shopee.callback');
        $timest = time();
        $sign = $this->signature->make($this->partnerId, $this->partnerKey, $path, $timest);
        $url = sprintf("%s%s?timestamp=%s&partner_id=%s&sign=%s&redirect=%s", $this->host, $path, $timest, $this->partnerId, $sign, $redirectUrl);

        return redirect()->away($url);
    }

    public function callback(Request $request, ShopeeAuthService $auth)
    {
        $code = $request->get('code');
        $shopId = $request->get('shop_id');

        $response = $auth->getTokenShopLevel($code, $shopId);
        if (empty($response['expire_in'])) {
            return $response;
        }
        $response['expire_in_datetime'] = date('Y-m-d H:i:s', time() + $response['expire_in']);
        return $response;
    }

    public function refreshToken(int $shop_id, string $refresh_token, ShopeeAuthService $auth)
    {
        $response = $auth->getAccessTokenShopLevel($shop_id, $refresh_token);
        $response['expire_in_datetime'] = date('Y-m-d H:i:s', time() + $response['expire_in']);
        Store::updateStoreToken($shop_id, $response['access_token'], $response['refresh_token'], $response['expire_in']);
        return $response;
    }

    public function shopeeShopInfo(Request $request)
    {
        $acces_token = $request->get('access_token');
        $shop_id = $request->get('shop_id');
        $shopeeApiService = new ShopeeApiService($this->signature);
        $response = $shopeeApiService->getShopInfo($acces_token, $shop_id);
        dd($response);
    }

    public function shopeeGetProducts(Request $request)
    {
        $acces_token = $request->get('access_token');
        $shop_id = $request->get('shop_id');
        $shopeeApiService = new ShopeeApiService($this->signature);
        $response = $shopeeApiService->getProducts($acces_token, $shop_id);
        dd($response);
    }
}
