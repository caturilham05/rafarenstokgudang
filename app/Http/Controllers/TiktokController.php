<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\Tiktok\TiktokApiService;
use App\Services\Tiktok\TiktokAuthService;
use Illuminate\Http\Request;

class TiktokController extends Controller
{
    public function connect(TiktokAuthService $service)
    {
        return redirect(
            $service->getAuthorizationUrl(route('tiktok.callback'))
        );
    }

    public function callback(Request $request,TiktokAuthService $service) {
        abort_if(
            $request->state !== session('tiktok_state'),
            403
        );

        try {
            $token = $service->getAccessToken($request->code);
            logger()->info('TikTok Success Get Access Token', [$token]);
            if (!empty($token['error'])) {
                throw new \Exception($token['error']);
            }

            $api  = app(TiktokApiService::class);
            $shop = $api->get('/authorization/202309/shops', [], $token['access_token']);
            if (!empty($shop['code'])) {
                throw new \Exception(sprintf('%s.[%s]', $shop['message'], $shop['code']));
            }

            $shop_id = $shop['data']['shops'][0]['id'] ?? 0;

            $store = Store::updateOrCreate(
                [
                    'shop_id' => $shop_id,
                ],
                [
                    'marketplace_name'         => 'Tiktok',
                    'shop_id'                  => $shop_id,
                    'store_name'               => $token['seller_name'],
                    'access_token'             => $token['access_token'],
                    'refresh_token'            => $token['refresh_token'],
                    'chiper'                   => $token['chiper'],
                    'token_expires_at'         => date('Y-m-d H:i:s', $token['access_token_expire_in']),
                    'refresh_token_expires_at' => date('Y-m-d H:i:s', $token['refresh_token_expire_in'])
                ]
            );

            if (empty($store->id)) {
                throw new \Exception(sprintf('store %s failed to add in sistem, please try again later', $token['seller_name']));
            }

            return response()->json([
                'ok'      => 1,
                'message' => sprintf('add store %s to sistem successfully', $token['seller_name'])
            ]);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()]);
        }
    }

    public function refreshToken(string $shop_id, string $refresh_token, TiktokAuthService $auth)
    {
        $response = $auth->refreshToken($refresh_token);
        Store::updateStoreToken(
            $shop_id,
            $response['access_token'],
            $response['refresh_token'],
            $response['access_token_expire_in'],
            $response['refresh_token_expire_in'], 'tiktok'
        );

        return $response;
    }
}
