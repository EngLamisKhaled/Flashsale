<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    //
    protected $fillable = [
        'product_id',
        'hold_id',
        'qty',
        'total_price',
        'status',
    ];
}
