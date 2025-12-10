<?php

namespace App\Services\Shopee;

use Illuminate\Support\Facades\Http;

class ShopeeAuthService
{
    protected $signature;

    public function __construct(ShopeeSignature $signature)
    {
        $this->signature = $signature;
    }

    /**
     * Process callback ?code & ?shop_id
     * lalu tukarkan ke access_token Shopee
     */
    public function getAccessToken(string $code, string $shopId)
    {
        $partnerId  = env('SHOPEE_PARTNER_ID_TEST');
        $partnerKey = env('SHOPEE_PARTNER_KEY_TEST');
        $timestamp  = time();

        $path = "/api/v2/auth/token/get";

        $bodyArr = [
            "code"       => $code,
            "shop_id"    => (int)$shopId,
            "partner_id" => (int)$partnerId,
        ];

        $bodyJson = json_encode($bodyArr);
        dd($bodyJson);
        // Generate signature
        $sign = $this->signature->make($partnerKey, $path, $timestamp, $bodyJson);

        // Sandbox base URL
        $host = env('SHOPEE_REDIRECT_URL_TEST');
        $url = "{$host}{$path}"
             . "?partner_id={$partnerId}&timestamp={$timestamp}&sign={$sign}";

        // Request ke Shopee
        $response = Http::withHeaders([
            "Content-Type" => "application/json"
        ])->post($url, $bodyJson);

        return $response->json();
    }
}
