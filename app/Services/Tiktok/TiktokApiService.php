<?php

namespace App\Services\Tiktok;

use Illuminate\Support\Facades\Http;

class TiktokApiService
{
    protected string $appKey;
    protected string $appSecret;
    protected string $baseApiUrl;

    public function __construct()
    {
        $this->appKey     = env('TIKTOK_APP_KEY');
        $this->appSecret  = env('TIKTOK_APP_SECRET');
        $this->baseApiUrl = env('TIKTOK_HOST');
    }

    protected function generateSignature(string $path, array $params): string
    {
        ksort($params);

        $baseString = $this->appSecret . $path;

        foreach ($params as $key => $value) {
            $baseString .= $key . $value;
        }

        $baseString .= $this->appSecret;

        return hash_hmac('sha256', $baseString, $this->appSecret);
    }

    public function get(string $path, array $query = [], string $accessToken)
    {
        $timestamp = time();

        // Query params WAJIB untuk signature
        $queryParams = array_merge($query, [
            'app_key'    => $this->appKey,
            'timestamp'  => $timestamp,
        ]);

        // Generate sign (PENTING)
        $sign = $this->generateSignature($path, $queryParams);

        // Build full URL
        $url = $this->baseApiUrl . $path;

        return Http::withHeaders([
                'Content-Type'         => 'application/json',
                'x-tts-access-token'   => $accessToken,
            ])
            ->get($url, array_merge($queryParams, [
                'sign' => $sign,
            ]))
            ->json();
    }

}
