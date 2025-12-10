<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Order extends Model
{
    use SoftDeletes;

    protected $table = 'orders';

    protected $fillable = [
        'invoice',
        'store_id',
        'customer_name',
        'customer_phone',
        'customer_address',
        'courier',
        'qty',
        'discount',
        'shipping_cost',
        'total_price',
        'status'
    ];
}
