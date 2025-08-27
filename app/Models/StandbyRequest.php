<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;

class StandbyRequest extends Model
{
    protected $fillable = [
        'trip_id','route_id','date',
        'seats_requested','allow_partial',
        'name','surname','email','phone',
        'amount','currency_code','amount_uah','fx_rate',
        'order_reference','w4p_invoice_id','w4p_auth_code',
        'status','wait_until',
        'authorized_at','matched_at','captured_at','voided_at',
        'booking_ids',
    ];

    protected $casts = [
        'date'          => 'date',
        'booking_ids'   => AsArrayObject::class,
        'authorized_at' => 'datetime',
        'matched_at'    => 'datetime',
        'captured_at'   => 'datetime',
        'voided_at'     => 'datetime',
        'wait_until'    => 'datetime',
        'amount'        => 'decimal:2',
        'amount_uah'    => 'decimal:2',
        'fx_rate'       => 'decimal:6',
    ];
}
