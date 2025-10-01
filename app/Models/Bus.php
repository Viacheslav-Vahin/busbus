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

    /** Автобуси, що мають посадку на $fromStopId і висадку на $toStopId */
    public function scopeForStops(Builder $q, int $fromStopId, int $toStopId): Builder
    {
        return $q
            ->whereHas('busStops', fn ($qq) =>
            $qq->where('type', 'boarding')->where('stop_id', $fromStopId)
            )
            ->whereHas('busStops', fn ($qq) =>
            $qq->where('type', 'dropping')->where('stop_id', $toStopId)
            );
    }
    public function seats()
    {
        return $this->hasMany(\App\Models\BusSeat::class);
    }

    /* ===================== Допоміжне ===================== */

    /** Обережне отримання масиву з JSON / mixed */
    public static function arr($val): array
    {
        if (is_array($val)) return $val;
        if (is_string($val)) {
            $d = json_decode($val, true);
            return is_array($d) ? $d : [];
        }
        return [];
    }

    /** Місткість: спершу seats_count, інакше — з seat_layout */
    public function capacity(): int
    {
        $count = (int)($this->seats_count ?? 0);
        if ($count > 0) return $count;

        $layout = self::arr($this->seat_layout);
        if (!$layout) return 0;

        $seatCount = 0;
        foreach ($layout as $item) {
            $type = is_array($item) ? strtolower((string)($item['type'] ?? '')) : '';
            if ($type === 'seat' || $type === 'chair' || $type === 's') {
                $seatCount++;
            }
        }
        return $seatCount;
    }

    /* ===================== Логіка розкладу ===================== */

    /** Чи працює автобус у конкретну дату (головне джерело — operation_days, потім weekly, потім schedules) */
    public function worksOnDate(\DateTimeInterface|string $date): bool
    {
        $d   = $date instanceof \DateTimeInterface ? Carbon::instance($date) : Carbon::parse($date);
        $ds  = $d->toDateString();
        $dow = strtolower($d->locale('en')->isoFormat('dddd')); // monday..sunday

        $offDays = collect(self::arr($this->off_days))
            ->map(fn($x) => is_array($x) ? ($x['date'] ?? null) : null)
            ->filter()->values()->all();
        if (in_array($ds, $offDays, true)) {
            return false;
        }

        $opDays = collect(self::arr($this->operation_days))
            ->map(fn($x) => is_array($x) ? ($x['date'] ?? null) : null)
            ->filter()->values()->all();

        if (!empty($opDays)) {
            return in_array($ds, $opDays, true);
        }

        $weekly = array_map(
            fn($x) => strtolower((string)$x),
            self::arr($this->weekly_operation_days)
        );
        if (!empty($weekly)) {
            return in_array($dow, $weekly, true);
        }

        if (method_exists($this, 'schedules')) {
            return $this->schedules()->whereDate('date', $ds)->exists();
        }

        // якщо немає жодних джерел — не працює
        return false;
    }

    /** Список дозволених дат у проміжку */
    public function allowedDates(Carbon $start, int $days = 180): array
    {
        $end = $start->copy()->addDays($days);
        $dates = [];

        foreach (self::arr($this->operation_days) as $od) {
            $d = is_array($od) ? ($od['date'] ?? null) : null;
            if ($d && $d >= $start->toDateString() && $d <= $end->toDateString()) {
                $dates[] = $d;
            }
        }

        $weekly = self::arr($this->weekly_operation_days);
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
            $qq->where('has_operation_days', true)
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
        $ops    = self::arr($this->operation_days);
        $weekly = self::arr($this->weekly_operation_days);
        return ($this->has_operation_days ?? false) || !empty($ops) || !empty($weekly);
    }
}
