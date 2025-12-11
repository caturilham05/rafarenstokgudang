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
    ];

    public static function getStores($storeId = null)
    {
        if ($storeId) {
            return self::findOrFail($storeId);
        }
        return self::where('deleted_at', null)->get();
    }

    public static function updateStoreToken($shopId, $accessToken, $refreshToken, $expiresIn)
    {
        $store = self::where('shop_id', $shopId)->first();
        if ($store) {
            $store->access_token = $accessToken;
            $store->refresh_token = $refreshToken;
            $store->token_expires_at = date('Y-m-d H:i:s', time() + $expiresIn);
            $store->save();
        }
        return $store;
    }
}
