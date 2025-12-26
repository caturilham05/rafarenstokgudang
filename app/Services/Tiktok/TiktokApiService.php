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

    public function generateSignature(string $path, array $params, array $body = [])
    {
        // 1. Filter out 'access_token' and 'sign' (Step 1)
        $excludeKeys = ['access_token', 'sign'];
        $filteredParams = array_filter($params, function ($key) use ($excludeKeys) {
            return !in_array($key, $excludeKeys);
        }, ARRAY_FILTER_USE_KEY);

        // 2. Sort keys alphabetically (Step 1)
        ksort($filteredParams);

        // 3. Concatenate parameters as {key}{value} (Step 2)
        $paramString = '';
        foreach ($filteredParams as $key => $value) {
            $paramString .= $key . $value;
        }

        // 4. Combine Path + ParamString (Step 3)
        $signString = $path . $paramString;

        // 5. Append Body if not empty (Step 4)
        if (!empty($body)) {
            // TikTok membutuhkan JSON string tanpa spasi antar elemen
            $signString .= json_encode($body);
        }

        // 6. Wrap with app_secret (Step 5)
        $wrapString = $this->appSecret . $signString . $this->appSecret;

        // 7. HMAC-SHA256 (Step 6)
        return hash_hmac('sha256', $wrapString, $this->appSecret);
    }

    public function get(string $path, array $query = [], string $accessToken)
    {
        $timestamp = time();

        // 1. Siapkan Query params
        $queryParams = array_merge($query, [
            'app_key'   => $this->appKey,
            'timestamp' => $timestamp,
        ]);

        // 2. Generate sign (Kirim array kosong [] sebagai body karena ini GET)
        $sign = $this->generateSignature($path, $queryParams, []);

        // 3. Tambahkan sign dan access_token ke query params untuk dikirim di URL
        $queryParams['sign'] = $sign;
        $queryParams['access_token'] = $accessToken;

        // 4. Bangun full URL dengan query string eksplisit
        $url = $this->baseApiUrl . $path . '?' . http_build_query($queryParams);

        return Http::withHeaders([
                'Content-Type'       => 'application/json',
                'x-tts-access-token' => $accessToken,
            ])
            ->get($url)
            ->json();
    }

    public function post(string $path, array $query = [], array $body = [], string $accessToken)
    {
        $timestamp = time();

        $queryParams = array_merge($query, [
            'app_key'    => $this->appKey,
            'timestamp'  => $timestamp,
        ]);

        // Kirim $body ke signature generator!
        $sign = $this->generateSignature($path, $queryParams, $body);

        $queryParams['sign'] = $sign;
        $queryParams['access_token'] = $accessToken; // TikTok butuh access_token di URL juga

        $url = $this->baseApiUrl . $path . '?' . http_build_query($queryParams);

        return Http::withHeaders([
                'Content-Type'       => 'application/json',
                'x-tts-access-token' => $accessToken,
            ])
            ->post($url, $body)
            ->json();
    }
}
