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


    /**
     * Get the user associated with the OrderReturn
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function order(): HasOne
    {
        return $this->hasOne(Order::class, 'order_id', 'id');
    }
}
