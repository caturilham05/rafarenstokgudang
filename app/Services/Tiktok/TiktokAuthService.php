<?php

namespace App\Services\Tiktok;

use App\Models\Store;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TiktokAuthService
{
    protected string $appKey;
    protected string $appSecret;
    protected string $baseAuthUrl = 'https://auth.tiktok-shops.com';

    public function __construct(Store $store)
    {
        $this->appKey    = $store->app_key;
        $this->appSecret = $store->app_secret;
    }

    public function getAuthorizationUrl(string $redirectUri, string $state): string
    {
        return $this->baseAuthUrl . '/oauth/authorize?' . http_build_query([
            'app_key'      => $this->appKey,
            'redirect_uri' => $redirectUri,
            'state'        => $state,
        ]);
    }

    public function getAccessToken(string $code): array
    {
        try {
            $data = [
                    'app_key'    => $this->appKey,
                    'app_secret' => $this->appSecret,
                    'grant_type' => 'authorized_code',
                    'auth_code'  => $code,
            ];

            logger()->info('Data Request Token', $data);

            $response = Http::get(
                'https://auth.tiktok-shops.com/api/v2/token/get',
                [
                    'app_key'    => $this->appKey,
                    'app_secret' => $this->appSecret,
                    'grant_type' => 'authorized_code',
                    'auth_code'  => $code,
                ]
            )->json();

            if (!empty($response['code'])) {
                throw new \Exception($response['message'].'.['.$response['code'].']');
            }

            return $response['data'];
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }

    public function refreshToken(string $refreshToken): array
    {
        try {
            $response = Http::get(
                'https://auth.tiktok-shops.com/api/v2/token/refresh',
                [
                    'app_key'       => $this->appKey,
                    'app_secret'    => $this->appSecret,
                    'grant_type'    => 'refresh_token',
                    'refresh_token' => $refreshToken,
                ]
            )->json();

            if (!empty($response['code'])) {
                throw new \Exception($response['message'].'.['.$response['code'].']');
            }

            return $response['data'];
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }
}
