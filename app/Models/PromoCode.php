<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PromoCode extends Model
{
    protected $fillable = [
        'code','type','value','max_uses','per_user_limit','min_amount',
        'starts_at','ends_at','is_active','used','meta'
    ];
    protected $casts = [
        'starts_at'=>'datetime','ends_at'=>'datetime','is_active'=>'boolean',
        'value'=>'decimal:2','min_amount'=>'decimal:2','meta'=>'array'
    ];

    public function scopeActive($q) {
        $now = now();
        return $q->where('is_active', true)
            ->where(function($qq) use ($now){ $qq->whereNull('starts_at')->orWhere('starts_at','<=',$now); })
            ->where(function($qq) use ($now){ $qq->whereNull('ends_at')->orWhere('ends_at','>=',$now); });
    }

    public static function normalize(string $code): string {
        return Str::upper(trim($code));
    }
}
