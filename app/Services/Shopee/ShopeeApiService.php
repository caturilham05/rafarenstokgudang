<?php

namespace App\Services\Shopee;

use Illuminate\Support\Facades\Http;

class ShopeeApiService
{
    protected $signature;

    public function __construct(ShopeeSignature $signature)
    {
        $this->signature = $signature;
    }

    /**
     * Contoh request ke Shopee API
     * misalnya: get merchant info / shop info
     */
    public function getShopInfo($accessToken, $shopId)
    {
        $partnerId  = env('SHOPEE_PARTNER_ID_TEST');
        $partnerKey = env('SHOPEE_PARTNER_KEY_TEST');

        $timestamp  = time();
        $path = "/api/v2/shop/get_shop_info";

        $body = json_encode([
            "shop_id" => (int)$shopId
        ]);

        // signature
        $sign = $this->signature->make($partnerKey, $path, $timestamp, $body);
        $host = env('SHOPEE_REDIRECT_URL_TEST');
        $url = "($host)}{$path}"
             . "?partner_id={$partnerId}&timestamp={$timestamp}&sign={$sign}&shop_id={$shopId}";

        $response = Http::withHeaders([
            "Content-Type" => "application/json",
            "Authorization" => "Bearer " . $accessToken
        ])->post($url, $body);

        return $response->json();
    }
}
