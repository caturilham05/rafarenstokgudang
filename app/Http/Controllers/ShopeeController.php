<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Shopee\ShopeeAuthService;
use App\Services\Shopee\ShopeeSignature;
use App\Services\Shopee\ShopeeApiService;
use App\Models\Store;
use Illuminate\Support\Facades\Log;

class ShopeeController extends Controller
{
    protected $signature;
    protected $partnerId;
    protected $partnerKey;
    protected $host;
    public function __construct(ShopeeSignature $signature)
    {
        $this->signature  = $signature;
        $this->partnerId  = env('SHOPEE_PARTNER_ID');
        $this->partnerKey = env('SHOPEE_PARTNER_KEY');
        $this->host       = env('SHOPEE_HOST');
    }

    public function shopee_redirect_auth_demo()
    {
        $path        = "/api/v2/shop/auth_partner";
        $redirectUrl = route('shopee.callback');
        $timest      = time();
        $sign        = $this->signature->make($this->partnerId, $this->partnerKey, $path, $timest);
        $url         = sprintf("%s%s?timestamp=%s&partner_id=%s&sign=%s&redirect=%s", $this->host, $path, $timest, $this->partnerId, $sign, $redirectUrl ?? '');

        return redirect()->away($url);
    }

    public function callback(Request $request, ShopeeAuthService $auth)
    {
        try {
            $code   = $request->get('code');
            $shopId = $request->get('shop_id');
            Log::channel('shopee')->info(sprintf('code: %s, shop_id: %s', $code, $shopId));

            $response = $auth->getTokenShopLevel($code, $shopId);
            if (!empty($response['error'])) {
                throw new \Exception($response['error']);
            }

            $response['expire_in_datetime'] = !empty($response['expire_in']) ? date('Y-m-d H:i:s', time() + $response['expire_in']) : null;
            $shopeeApiService               = app(ShopeeApiService::class);
            $shop_info                      = $shopeeApiService->getShopInfo($response['access_token'], $shopId);

            if (!empty($shop_info['error'])) {
                throw new \Exception($shop_info['error']);
            }

            Store::updateOrCreate(
                [
                    'shop_id' => $shopId
                ],
                [
                    'marketplace_name' => 'Shopee',
                    'shop_id'          => $shopId,
                    'store_name'       => $shop_info['shop_name'],
                    'marketplace_id'   => '2014278',
                    'access_token'     => $response['access_token'],
                    'refresh_token'    => $response['refresh_token'],
                    'token_expires_at' => $response['expire_in_datetime']
                ]
            );

            return response()->json([
                'error'   => null,
                'status'  => 'success',
                'message' => sprintf('request access token for store %s success', $shop_info['shop_name'])
            ]);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()]);
        }
    }

    public function refreshToken(int $shop_id, string $refresh_token, ShopeeAuthService $auth)
    {
        try {
            $response                       = $auth->getAccessTokenShopLevel($shop_id, $refresh_token);
            $response['expire_in_datetime'] = !empty($response['expire_in']) ? date('Y-m-d H:i:s', time() + $response['expire_in']) : null;
            Store::updateStoreToken($shop_id, $response['access_token'], $response['refresh_token'], $response['expire_in']);
            return $response;
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()]);
        }
    }

    public function shopeeShopInfo(Request $request)
    {
        try {
            $acces_token      = $request->get('access_token');
            $shop_id          = $request->get('shop_id');
            $shopeeApiService = new ShopeeApiService($this->signature);
            $response         = $shopeeApiService->getShopInfo($acces_token, $shop_id);
            return $response->json();
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()]);
        }
    }

    public function shopeeGetProducts(Request $request)
    {
        try {
            $acces_token      = $request->get('access_token');
            $shop_id          = $request->get('shop_id');
            $shopeeApiService = new ShopeeApiService($this->signature);
            $response         = $shopeeApiService->getProducts($acces_token, $shop_id);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()]);
        }
    }
}
