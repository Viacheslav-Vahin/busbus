<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceRule extends Model
{
    protected $fillable = [
        'scope_type','scope_id','seat_number','amount_uah','priority',
        'days_of_week','starts_at','ends_at','is_active'
    ];
    protected $casts = [
        'amount_uah'=>'decimal:2','priority'=>'integer','is_active'=>'boolean',
        'days_of_week'=>'array','starts_at'=>'datetime','ends_at'=>'datetime'
    ];
}
