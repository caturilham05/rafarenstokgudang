<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class Order extends Model
{
    use SoftDeletes;

    protected $table = 'orders';

    protected $fillable = [
        'invoice',
        'store_id',
        'marketplace_name',
        'store_name',
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
        'commission_fee',
        'delivery_seller_protection_fee_premium_amount',
        'service_fee',
        'seller_order_processing_fee',
        'order_time',
        'buyer_username',
        'payment_method',
        'notes',
        'waybill',
        'packer_id',
        'packer_name',
        'scanned_at',
        'is_printed'
    ];

    protected $appends = ['marketplace_fee'];
    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    public function orderProducts(): HasMany
    {
        return $this->hasMany(OrderProduct::class);
    }

    public function getMarketplaceFeeAttribute(): float
    {
        return
            ($this->voucher_from_seller ?? 0) +
            ($this->commission_fee ?? 0) +
            ($this->delivery_seller_protection_fee_premium_amount ?? 0) +
            ($this->service_fee ?? 0) +
            ($this->seller_order_processing_fee ?? 0);
    }

    /**
     * Get the user that owns the Order
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function packer(): BelongsTo
    {
        return $this->belongsTo(Packer::class);
    }

    protected function scannedAtFormatted(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->scanned_at
                ? $this->scanned_at->locale('id')->translatedFormat('j F Y H:i:s')
                : null
        );
    }
}
