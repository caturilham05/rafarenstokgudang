<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderProduct extends Model
{
    protected $table = 'order_products';

    protected $fillable = [
        'order_id',
        'product_id',
        'qty',
        'price',
        'sale',
        'discount'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
