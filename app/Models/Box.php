<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Box extends Model
{
    protected $table = 'boxes';
    protected $fillable = [
        'sku',
        'qty',
        'qty_out',
        'last_scanned_out'
    ];

    protected $casts = [
        'last_scanned_out' => 'datetime',
        'created_at'       => 'datetime'
    ];

    protected function createdAtFormatted(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->created_at
                ? $this->created_at->locale('id')->translatedFormat('j F Y H:i:s')
                : null
        );
    }

    protected function scannedAtFormatted(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->last_scanned_out
                ? $this->last_scanned_out->locale('id')->translatedFormat('j F Y H:i:s')
                : null
        );
    }
}
