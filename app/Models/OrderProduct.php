<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        'discount',
        'is_bpom_checked',
        'bpom_checked_qty',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function productMaster()
    {
        return $this->hasOneThrough(
            ProductMaster::class,
            ProductMasterItem::class,
            'product_id',
            'id',
            'product_id',
            'product_master_id'
        );
    }

    public function productMasters(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductMaster::class,
            'product_master_items',
            'product_id',
            'product_master_id',
            'product_id',
            'id'
        )->withPivot('stock_conversion');
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
