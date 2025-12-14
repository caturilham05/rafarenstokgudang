<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'status',
        'voucher_from_seller',
        'commision_fee',
        'delivery_seller_protection_fee_premium_amount',
        'service_fee',
        'seller_order_processing_fee',
        'order_time',
        'buyer_username',
        'payment_method',
        'notes',
    ];

    public function orderProducts(): HasMany
    {
        return $this->hasMany(OrderProduct::class);
    }
}
