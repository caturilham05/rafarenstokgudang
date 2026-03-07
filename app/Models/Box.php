<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Box extends Model
{
    protected $table = 'boxes';
    protected $fillable = [
        'sku',
        'qty'
    ];
}
