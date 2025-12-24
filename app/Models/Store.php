<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use SoftDeletes;
    protected $table = 'stores';
    protected $fillable = [
        'marketplace_name',
        'store_name',
        'store_url',
        'marketplace_id',
        'shop_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'refresh_token_expires_at'
    ];

    public static function getStores($storeId = null)
    {
        if ($storeId) {
            return self::where('shop_id', $storeId)->get();
        }
        return self::where('deleted_at', null)->get();
    }

    public static function updateStoreToken($shopId, $accessToken, $refreshToken, $expiresIn , $refreshTokenExpires = null, $marketplaceName = 'shopee')
    {
        $store = self::where('shop_id', $shopId)->first();
        if ($store) {
            $store->access_token             = $accessToken;
            $store->refresh_token            = $refreshToken;
            $store->token_expires_at         = preg_match('/shopee/', $marketplaceName) ? date('Y-m-d H:i:s', time() + $expiresIn) : (preg_match('/tiktok/', $marketplaceName) ? date('Y-m-d H:i:s', $expiresIn) : NULL);
            $store->refresh_token_expires_at = preg_match('/tiktok/', $marketplaceName) ? date('Y-m-d H:i:s', $refreshTokenExpires) : NULL;
            $store->save();
        }
        return $store;
    }
}
