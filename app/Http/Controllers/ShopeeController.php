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

    public function shopee_redirect_auth_demo()
    {
        $path = "/api/v2/shop/auth_partner";
        $redirectUrl = "http://demo.rafarenstokgudang.com/";
        $timest = time();
        $baseString = sprintf("%s%s%s", env('SHOPEE_PARTNER_ID_TEST'), $path, $timest);
        $sign = $this->signature->make(env('SHOPEE_PARTNER_KEY_TEST'), $path, $timest);
        $url = sprintf("%s%s?timestamp=%s&partner_id=%s&sign=%s&redirect=%s", env('SHOPEE_REDIRECT_URL_TEST'), $path, $timest, env('SHOPEE_PARTNER_ID_TEST'), $sign, $redirectUrl);
        dd($url, $_GET);
    }

    public function callback(Request $request, ShopeeAuthService $auth)
    {
        $code = $request->get('code');
        $shopId = $request->get('shop_id');

        return $auth->getAccessToken($code, $shopId);
    }
}
