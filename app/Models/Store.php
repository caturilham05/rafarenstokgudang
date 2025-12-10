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
}
