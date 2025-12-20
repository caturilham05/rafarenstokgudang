<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderProduct extends Model
{
    protected $table = 'order_products';

    protected $fillable = [
        'order_id',
        'product_id',
        'varian',
        'product_name',
        'product_model_id',
        'product_online_id',
        'qty',
        'price',
        'sale',
        'discount'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the user associated with the OrderProduct
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function Order(): HasOne
    {
        return $this->hasOne(Order::class, 'id', 'order_id');
    }
}
