<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductMaster extends Model
{
    protected $table = 'product_masters';
    protected $fillable = [
        'product_name',
        'stock',
        'sale'
    ];

    /**
     * Get all of the comments for the ProductMaster
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function items(): HasMany
    {
        return $this->hasMany(ProductMasterItem::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_master_items',
            'product_master_id',
            'product_id'
        );
    }
}
