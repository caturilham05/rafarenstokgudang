<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
        'sold'
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
