<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderReturn extends Model
{
    use SoftDeletes;

    protected $table    = 'order_returns';
    protected $fillable = [
        'order_id',
        'invoice_order',
        'invoice_return',
        'waybill',
        'buyer_username',
        'courier',
        'reason',
        'reason_text',
        'refund_amount',
        'return_time',
        'status',
        'status_logistic',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'invoice_order', 'invoice');
    }
}
