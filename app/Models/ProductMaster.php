<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductMaster extends Model
{
    protected $table = 'product_masters';
    protected $fillable = [
        'product_id',
        'product_name',
        'stock',
        'stock_conversion',
        'sale'
    ];

    /**
     * Get the user that owns the ProductMaster
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
