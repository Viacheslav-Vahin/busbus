<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class Bus extends Model
{
    use HasFactory;

    protected $casts = [
        'has_operation_days'     => 'boolean',
        'operation_days'         => 'array',   // [{date:"YYYY-MM-DD"}]
        'has_off_days'           => 'boolean',
        'off_days'               => 'array',   // [{date:"YYYY-MM-DD"}]
        'seat_configuration'     => 'array',
        'seat_layout'            => 'array',
        'schedule_type'          => 'string',
        'weekly_operation_days'  => 'array',   // ["Monday","Tuesday",...]
    ];

    protected $fillable = [
        'name',
        'seats_count',
        'registration_number',
        'description',
        'seat_layout',
        'route_id',
        'schedule_type',
        'weekly_operation_days',
        'has_operation_days',
        'operation_days',
        'has_off_days',
        'off_days',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->has_operation_days = $model->has_operation_days ?? false;
            $model->has_off_days       = $model->has_off_days ?? false;
        });

        // якщо seat_layout чомусь прийшов рядком — розкодовуємо
        static::saving(function ($bus) {
            if (is_string($bus->seat_layout)) {
                $decoded = json_decode($bus->seat_layout, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $bus->seat_layout = $decoded;
                }
            }
        });
    }

    /* ===================== Релейшени ===================== */

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function schedules()
    {
        return $this->hasMany(RouteSchedule::class);
    }

    public function operationDays()
    {
        return $this->hasMany(OperationDay::class);
    }

    public function offDays()
    {
        return $this->hasMany(OffDay::class);
    }

    public function stops()
    {
        return $this->belongsToMany(Stop::class, 'bus_stops')
            ->withPivot('type', 'time')
            ->withTimestamps();
    }

    public function busStops()
    {
        return $this->hasMany(BusStop::class);
    }

    public function boarding_points()
    {
        return $this->hasMany(BusStop::class)->where('type', 'boarding');
    }

    public function droppingPoints()
    {
        return $this->hasMany(BusStop::class)->where('type', 'dropping');
    }

    public function seats()
    {
        return $this->hasMany(\App\Models\BusSeat::class);
    }

    /** Масове збереження зупинок */
    public function saveBusStops($bus, $data)
    {
        $bus->busStops()->delete();

        if (isset($data['boarding_points'])) {
            foreach ($data['boarding_points'] as $boardingPoint) {
                $bus->busStops()->create([
                    'stop_id' => $boardingPoint['stop_id'],
                    'type'    => 'boarding',
                    'time'    => $boardingPoint['time'],
                ]);
            }
        }

        if (isset($data['dropping_points'])) {
            foreach ($data['dropping_points'] as $droppingPoint) {
                $bus->busStops()->create([
                    'stop_id' => $droppingPoint['stop_id'],
                    'type'    => 'dropping',
                    'time'    => $droppingPoint['time'],
                ]);
            }
        }
    }

    /* ===================== Scopes ===================== */

    /** Автобуси, що мають посадку на $fromStopId і висадку на $toStopId */
    public function scopeForStops($q, int $fromStopId, int $toStopId)
    {
        return $q->whereHas('busStops', fn($qq) => $qq->where('type', 'boarding')->where('stop_id', $fromStopId))
            ->whereHas('busStops', fn($qq) => $qq->where('type', 'dropping')->where('stop_id', $toStopId));
    }

    /* ===================== Логіка розкладу ===================== */

    /** Чи працює автобус у конкретну дату */
    public function worksOnDate(\DateTimeInterface|string $date): bool
    {
        $d   = $date instanceof \DateTimeInterface ? Carbon::instance($date) : Carbon::parse($date);
        $ds  = $d->toDateString();
        $dow = $d->format('l'); // Monday..Sunday

        $off = collect($this->off_days ?? [])->pluck('date')->all();
        if (in_array($ds, $off, true)) {
            return false;
        }

        if ($this->has_operation_days && !empty($this->operation_days)) {
            $ops = collect($this->operation_days)->pluck('date')->all();
            return in_array($ds, $ops, true);
        }

        $weekly = (array) ($this->weekly_operation_days ?? []);
        if (!empty($weekly)) {
            return in_array($dow, $weekly, true);
        }

        return true;
    }

    /** Список дозволених дат у проміжку */
    public function allowedDates(Carbon $start, int $days = 180): array
    {
        $end = $start->copy()->addDays($days);
        $dates = [];

        foreach ((array)($this->operation_days ?? []) as $od) {
            $d = is_array($od) ? ($od['date'] ?? null) : null;
            if ($d && $d >= $start->toDateString() && $d <= $end->toDateString()) {
                $dates[] = $d;
            }
        }

        $weekly = (array)($this->weekly_operation_days ?? []);
        if ($weekly) {
            $cur = $start->copy();
            while ($cur->lte($end)) {
                if ($this->worksOnDate($cur)) {
                    $dates[] = $cur->toDateString();
                }
                $cur->addDay();
            }
        }

        return array_values(array_unique($dates));
    }

    // є якийсь розклад (або конкретні дати, або тижневий графік)
    public function scopeHasAnySchedule(Builder $q): Builder
    {
        return $q->where(function ($qq) {
            // якщо є службові прапорці — використовуємо їх
            $qq->where('has_operation_days', true)
                // або фактичні JSON масиви
                ->orWhere(function ($q) {
                    $q->whereNotNull('operation_days')
                        ->whereRaw('JSON_LENGTH(operation_days) > 0');
                })
                ->orWhere(function ($q) {
                    $q->whereNotNull('weekly_operation_days')
                        ->whereRaw('JSON_LENGTH(weekly_operation_days) > 0');
                });
        });
    }

    public function hasAnySchedule(): bool
    {
        $ops    = (array) ($this->operation_days ?? []);
        $weekly = (array) ($this->weekly_operation_days ?? []);
        return ($this->has_operation_days ?? false) || !empty($ops) || !empty($weekly);
    }
}
