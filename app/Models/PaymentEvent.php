<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentEvent extends Model
{
    //
    protected $fillable = [
        'order_id',
        'idempotency_key',
        'status',
    ];
}
