<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Packer extends Model
{
    protected $table = 'packers';
    protected $fillable = [
        'packer_name'
    ];
}
