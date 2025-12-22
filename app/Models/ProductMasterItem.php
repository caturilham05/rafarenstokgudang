<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMasterItem extends Model
{
    protected $table    = 'product_master_items';
    protected $fillable = ['product_master_id', 'product_id'];

    /**
     * Item ini milik Product Master
     */
    public function productMaster(): BelongsTo
    {
        return $this->belongsTo(ProductMaster::class);
    }

    /**
     * Item ini menunjuk ke Product Marketplace
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
