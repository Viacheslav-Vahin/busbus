<?php
// BusBookingSystem/app/Models/Booking.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

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
        $adultPrice = $this->route->ticket_price;
        $childDiscount = 0.5;
        return ($adultTickets * $adultPrice) + ($childTickets * $adultPrice * $childDiscount);
    }

    public function getPassengerNamesAttribute(): string
    {
        $passengers = $this->passengers;

        if (is_string($passengers)) {
            $passengers = json_decode($passengers, true) ?: [];
        }

        return collect($passengers)
            ->pluck('name')
            ->implode(', ');
    }
    public function getPassengerNoteAttribute(): string
    {
        $passengers = $this->passengers;

        if (is_string($passengers)) {
            $passengers = json_decode($passengers, true) ?: [];
        }

        return collect($passengers)
            ->pluck('note')
            ->implode(', ');
    }
    public function getPassengerPhoneAttribute(): string
    {
        $passengers = $this->passengers;

        if (is_string($passengers)) {
            $passengers = json_decode($passengers, true) ?: [];
        }

        return collect($passengers)
            ->pluck('phone_number')
            ->implode(', ');
    }
    public function getPassengerEmailAttribute(): string
    {
        $passengers = $this->passengers;

        if (is_string($passengers)) {
            $passengers = json_decode($passengers, true) ?: [];
        }

        return collect($passengers)
            ->pluck('email')
            ->implode(', ');
    }
    public function route()
    {
        return $this->belongsTo(\App\Models\Route::class, 'route_id');
    }

    public function getRouteDisplayAttribute(): string
    {
        if ($this->route) {
            return $this->route->start_point . ' - ' . $this->route->end_point;
        }
        return 'N/A';
    }
}
