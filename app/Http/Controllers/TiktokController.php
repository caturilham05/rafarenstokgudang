<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\Tiktok\TiktokApiService;
use App\Services\Tiktok\TiktokAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TiktokController extends Controller
{
    public function connect(Request $request)
    {
        $store = Store::findOrFail($request->store_id);

        $state = Str::random(32);

        session([
            "tiktok_oauth.$state" => [
                'store_id' => $store->id,
            ]
        ]);

        $auth = new TiktokAuthService($store);

        return redirect(
            $auth->getAuthorizationUrl(
                route('tiktok.callback'),
                $state
            )
        );
    }

    public function callback(Request $request)
    {
        $oauth = session("tiktok_oauth.$request->state");

        abort_if(!$oauth, 403, 'Invalid OAuth state');

        $store = Store::findOrFail($oauth['store_id']);

        try {
            $auth = new TiktokAuthService($store);

            $token = $auth->getAccessToken($request->code);
            logger()->info('TikTok Success Get Access Token', [$token]);

            if (!empty($token['error'])) {
                throw new \Exception($token['error']);
            }

            $api  = new TiktokApiService($store);
            $shop = $api->get('/authorization/202309/shops', [], $token['access_token']);
            logger()->info('shop', [$shop ?? []]);


            if (!empty($shop['code'])) {
                throw new \Exception(sprintf('%s.[%s]', $shop['message'], $shop['code']));
            }

            $shop_id = $shop['data']['shops'][0]['id'] ?? null;
            logger()->info('shop_id', [$shop_id ?? 0]);

            // if (!$shop_id || $shop_id != $store->shop_id) {
            //     throw new \Exception('Shop ID mismatch');
            // }

            // 🔥 UPDATE TOKEN SAJA
            $store->update([
                'access_token'             => $token['access_token'] ?? NULL,
                'refresh_token'            => $token['refresh_token'] ?? NULL,
                'chiper'                   => $token['chiper'] ?? NULL,
                'token_expires_at'         => now()->addSeconds($token['access_token_expire_in']) ?? NULL,
                'refresh_token_expires_at' => now()->addSeconds($token['refresh_token_expire_in']) ?? NULL,
            ]);

            session()->forget("tiktok_oauth.$request->state");

            return response()->json([
                'ok'      => 1,
                'message' => "Store {$store->store_name} reconnected successfully",
                'data' => [
                    'oauth' => $oauth,
                    'token' => $token,
                    'shop'  => $shop
                ]
            ]);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()]);
        }
    }


    public function refreshToken(string $shop_id)
    {
        $store = Store::where('shop_id', $shop_id)->firstOrFail();

        $auth = new TiktokAuthService($store);

        $response = $auth->refreshToken($store->refresh_token);

        if (!empty($response['error'])) {
            return $response;
        }

        $store->update([
            'access_token'             => $response['access_token'],
            'refresh_token'            => $response['refresh_token'],
            'token_expires_at'         => now()->addSeconds($response['access_token_expire_in']),
            'refresh_token_expires_at' => now()->addSeconds($response['refresh_token_expire_in']),
        ]);

        return $response;
    }
}
