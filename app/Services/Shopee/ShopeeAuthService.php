<?php

namespace App\Services\Shopee;

use Illuminate\Support\Facades\Http;

class ShopeeAuthService
{
    protected $signature;
    protected $partnerId;
    protected $partnerKey;
    protected $host;

    public function __construct(ShopeeSignature $signature)
    {
        $this->signature = $signature;
        $this->partnerId  = env('SHOPEE_PARTNER_ID_TEST');
        $this->partnerKey = env('SHOPEE_PARTNER_KEY_TEST');
        $this->host       = env('SHOPEE_HOST');
    }

    /**
     * Process callback ?code & ?shop_id
     * lalu tukarkan ke access_token Shopee
     */
    public function getTokenShopLevel(string $code, int $shopId)
    {
        $timestamp  = time();

        $path = "/api/v2/auth/token/get";

        $bodyArr = [
            "code"       => $code,
            "shop_id"    => intval($shopId),
            "partner_id" => intval($this->partnerId),
        ];

        $bodyJson = json_encode($bodyArr);

        $sign = $this->signature->make($this->partnerId, $this->partnerKey, $path, $timestamp);

        // Sandbox base URL
        $url = "{$this->host}{$path}"."?partner_id={$this->partnerId}&timestamp={$timestamp}&sign={$sign}";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json'
        ])->withBody($bodyJson, 'application/json')->post($url);

        return $response->json();
    }

    public function getAccessTokenShopLevel(int $shop_id, string $refresh_token)
    {
        $path = "/api/v2/auth/access_token/get";
        $timestamp  = time();
        $sign = $this->signature->make($this->partnerId, $this->partnerKey, $path, $timestamp);
        $url = sprintf("%s%s?partner_id=%s&timestamp=%s&sign=%s", $this->host, $path, $this->partnerId, $timestamp, $sign);

        $bodyArr = [
            "shop_id"    => intval($shop_id),
            "partner_id" => intval($this->partnerId),
            "refresh_token" => $refresh_token
        ];

        $bodyJson = json_encode($bodyArr);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json'
        ])->withBody($bodyJson, 'application/json')->post($url);

        return $response->json();
    }
}
