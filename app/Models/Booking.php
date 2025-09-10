<?php
// BusBookingSystem/app/Models/Booking.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Trip;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Booking extends Model
{
    use HasFactory;
    use LogsActivity;
    public function agent() { return $this->belongsTo(\App\Models\User::class, 'agent_id'); }

    protected $casts = [
        'is_solo_companion'   => 'boolean',
        'discount_pct'        => 'decimal:2',
        'date'            => 'date:Y-m-d',
        'paid_at'         => 'datetime',
        'checked_in_at'   => 'datetime',
        'held_until'      => 'datetime',
        'price'           => 'decimal:2',
        'price_uah'       => 'decimal:2',
        'fx_rate'         => 'decimal:6',
        'passengers'          => 'array',
        'additional_services' => 'array',
        'pricing'             => 'array',
        'payment_meta'       => 'array',
    ];

    // Вказуємо які поля можуть бути масово присвоєні
    protected $fillable = [
        'route_id','destination_id','trip_id','bus_id','selected_seat','passengers','date',
        'user_id','seat_number','price','additional_services','currency_code','status',
        'paid_at','payment_method','invoice_number','tax_rate','ticket_uuid','ticket_rev',
        'qr_path','ticket_pdf_path','checked_in_at','checked_in_by','checkin_place',
        'ticket_serial','order_id','hold_token','held_until','is_solo_companion','discount_pct',

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
            ->map(function($p){
                if (!empty($p['name'])) {
                    return trim((string)$p['name']); // старий формат
                }
                $fn = trim((string)($p['first_name'] ?? ''));
                $ln = trim((string)($p['last_name']  ?? ''));
                return trim($fn.' '.$ln);
            })
            ->filter()
            ->implode(', ');
    }
    public function getPassengerEmailAttribute(): string
    {
        $passengers = is_string($this->passengers) ? json_decode($this->passengers, true) ?: [] : ($this->passengers ?? []);
        return collect($passengers)->pluck('email')->filter()->implode(', ');
    }

    public function getPassengerPhoneAttribute(): string
    {
        $passengers = is_string($this->passengers) ? json_decode($this->passengers, true) ?: [] : ($this->passengers ?? []);
        return collect($passengers)->pluck('phone_number')->filter()->implode(', ');
    }

    public function getPassengerNoteAttribute(): string
    {
        $passengers = is_string($this->passengers) ? json_decode($this->passengers, true) ?: [] : ($this->passengers ?? []);
        return collect($passengers)->pluck('note')->filter()->implode(' | ');
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

    public function currency()
    {
        return $this->belongsTo(\App\Models\Currency::class, 'currency_code', 'code');
    }

    public function markAs(string $status): void
    {
        $this->status = $status;
        $this->paid_at = $status === 'paid' ? now() : null;
        $this->save();
    }
    public function bus()
    {
        return $this->belongsTo(\App\Models\Bus::class, 'bus_id');
    }

    public function destination()
    {
        // якщо destination_id посилається на Route або Stop — підстав правильну модель і ключ
        return $this->belongsTo(\App\Models\Route::class, 'destination_id');
    }

    public function scopePaid($q)
    {
        return $q->where('status', 'paid');
    }

    public function scopeUnpaid($q)
    {
        return $q->whereIn('status', ['hold', 'pending']);
    }
    protected static function booted()
    {
        static::creating(function (Booking $b) {
            if (empty($b->ticket_uuid)) {
                $b->ticket_uuid = (string) \Illuminate\Support\Str::uuid();
            }
            if (empty($b->ticket_serial)) {
                // Напр.: MAX-2025-000001 (короткий красивий номер)
                $seq = (Booking::max('id') ?? 0) + 1;
                $b->ticket_serial = 'MAX-'.now()->format('Y').'-'.str_pad($seq, 6, '0', STR_PAD_LEFT);
            }
        });
    }
    public function getStablePdfUrlAttribute(): string
    {
        return route('tickets.pdf', $this->ticket_uuid);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('booking')
            ->logOnly([
                'status',
                'price',
                'price_uah',
                'currency_code',
                'seat_number',
                'user_id',
                'promo_code',
                'discount_amount',
            ])
            ->logOnlyDirty()          // логувати лише змінені поля
            ->dontSubmitEmptyLogs();  // не писати пусті логи
    }

    // Не обовʼязково, але зручно для читабельності в журналі
    public function getDescriptionForEvent(string $eventName): string
    {
        return "Booking {$eventName} (ID: {$this->id})";
    }

    public function getAdditionalServiceIdsAttribute(): array {
        $raw = $this->additional_services ?? [];
        if (is_array($raw) && isset($raw['ids']) && is_array($raw['ids'])) $raw = $raw['ids'];
        return collect($raw)->flatten()->map(fn($i)=>is_array($i)?($i['id']??$i['service_id']??null):(is_numeric($i)?(int)$i:null))->filter()->values()->all();
    }
}
