<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;


class Product extends Model
{
    use SoftDeletes;

    protected $table = 'products';

    protected $fillable = [
        'store_id',
        'product_online_id',
        'product_model_id',
        'product_name',
        'price',
        'sale',
        'stock',
        'sold',
        'varian',
        'url_product',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public static function getProducts(string $product_online_id = null, string $product_model_id = null)
    {
        $query = self::query();

        if (!is_null($product_online_id)) {
            $query->where('product_online_id', $product_online_id);
            if (!is_null($product_model_id)) {
                $query->where('product_model_id', $product_model_id);
            }
        }

        return $query->get();
    }

    /**
     * Get the user associated with the Product
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function masterItem(): HasOne
    {
        return $this->hasOne(ProductMasterItem::class);
    }

    public function productMasters(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductMaster::class,
            'product_master_items',
            'product_id',
            'product_master_id'
        );
    }
}
