<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $casts = [
        'additional_services' => 'array',
    ];

    // Вказуємо які поля можуть бути масово присвоєні
    protected $fillable = [
        'trip_id',
        'user_id',
        'seat_number',
        'price',
        'additional_services',
    ];

    // Визначаємо зв'язки з іншими моделями
    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
