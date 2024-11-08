<?php
// BusBookingSystem/app/Models/Booking.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $casts = [
        'additional_services' => 'array',
        'passengers' => 'array',
    ];

    // Вказуємо які поля можуть бути масово присвоєні
    protected $fillable = [
        'trip_id',
        'user_id',
        'seat_number',
        'price',
        'additional_services',
        'bus_id',
        'route_id',
        'destination_id',
        'seat_number',
        'selected_seat',
        'date',
        'passengers',
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

    public function calculatePrice($adultTickets, $childTickets)
    {
        // Приклад обчислення з урахуванням знижок для дітей
        $adultPrice = $this->route->ticket_price; // Використовуємо ціну маршруту
        $childDiscount = 0.5; // 50% знижка для дітей
        return ($adultTickets * $adultPrice) + ($childTickets * $adultPrice * $childDiscount);
    }
}
