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

    public function get(string $endpoint, array $params, string $accessToken)
    {
        $params = array_merge($params, [
            'app_key'      => $this->appKey,
            'timestamp'    => time(),
            'access_token' => $accessToken,
        ]);

        $params['sign'] = TiktokSignature::generate(
            $params,
            $this->appSecret
        );

        return Http::get($this->baseApiUrl . $endpoint, $params)->json();
    }
}
