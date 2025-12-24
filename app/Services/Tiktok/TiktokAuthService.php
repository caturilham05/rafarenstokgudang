<?php

namespace App\Services\Tiktok;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TiktokAuthService
{
    protected string $appKey;
    protected string $appSecret;
    protected string $baseAuthUrl = 'https://auth.tiktok-shops.com';

    public function __construct()
    {
        $this->appKey    = env('TIKTOK_APP_KEY');
        $this->appSecret = env('TIKTOK_APP_SECRET');
    }

    /**
     * Generate redirect URL ke TikTok
     */
    public function getAuthorizationUrl(string $redirectUri): string
    {
        $state = Str::random(32);
        session(['tiktok_state' => $state]);


        logger()->info('TikTok OAuth Redirect', [
            'state'        => $state,
            'redirect_uri' => $redirectUri
        ]);


        return $this->baseAuthUrl . '/oauth/authorize?' . http_build_query([
            'app_key'      => $this->appKey,
            'redirect_uri' => $redirectUri,
            'state'        => $state,
        ]);
    }

    /**
     * Exchange auth code -> access token
     */
    public function getAccessToken(string $code): array
    {
        try {
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

    /**
     * Refresh token
     */
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
