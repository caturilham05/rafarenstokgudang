<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Shopee\ShopeeAuthService;
use App\Services\Shopee\ShopeeSignature;

class ShopeeController extends Controller
{
    protected $signature;
    public function __construct(ShopeeSignature $signature)
    {
        $this->signature = $signature;
    }

    public function shopee_redirect_auth_demo(Request $request)
    {
        $path = "/api/v2/shop/auth_partner";
        // $redirectUrl = "http://demo.rafarenstokgudang.com/";
        $redirectUrl = route('shopee.callback');
        $timest = time();
        $baseString = sprintf("%s%s%s", env('SHOPEE_PARTNER_ID_TEST'), $path, $timest);
        $sign = $this->signature->make(env('SHOPEE_PARTNER_KEY_TEST'), $path, $timest);
        $url = sprintf("%s%s?timestamp=%s&partner_id=%s&sign=%s&redirect=%s", env('SHOPEE_REDIRECT_URL_TEST'), $path, $timest, env('SHOPEE_PARTNER_ID_TEST'), $sign, $redirectUrl);
        return redirect()->away($url);
    }

    public function callback(Request $request, ShopeeAuthService $auth)
    {
        $code = $request->get('code');
        $shopId = $request->get('shop_id');
        dd($code, $shopId);

        return $auth->getAccessToken($code, $shopId);
    }
}
