<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

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

        // НЕ кодуємо seat_layout у рядок — каст 'array' зробить це сам.
        // Тут лише розкодовуємо, якщо раптом прийшов рядок JSON.
        static::saving(function ($bus) {
            if (is_string($bus->seat_layout)) {
                $decoded = json_decode($bus->seat_layout, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $bus->seat_layout = $decoded;
                }
            }
        });
    }

    /* ===================== Релейшени (без змін) ===================== */

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

    /**
     * Масове збереження зупинок (без змін у твоїй логіці).
     */
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

    /* ===================== Нормалізація дат ===================== */

    /**
     * Приймає:
     *  - рядок JSON;
     *  - ["2025-01-04", ...];
     *  - [{"date":"2025-01-04"}, ...]
     * і зберігає у вигляді [{"date":"YYYY-MM-DD"}, ...]
     */
    public function setOperationDaysAttribute($value): void
    {
        $this->attributes['operation_days'] = json_encode(
            $this->normalizeDateList($value),
            JSON_UNESCAPED_UNICODE
        );
    }

    public function setOffDaysAttribute($value): void
    {
        $this->attributes['off_days'] = json_encode(
            $this->normalizeDateList($value),
            JSON_UNESCAPED_UNICODE
        );
    }

    private function normalizeDateList($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }

        $out = [];
        foreach ((array) $value as $row) {
            if (is_string($row)) {
                $d = $this->fmtDate($row);
                if ($d) $out[] = ['date' => $d];
                continue;
            }
            if (is_array($row)) {
                $d = $row['date'] ?? (is_string(reset($row)) ? reset($row) : null);
                $d = $this->fmtDate($d);
                if ($d) $out[] = ['date' => $d];
            }
        }

        return $out;
    }

    private function fmtDate(?string $s): ?string
    {
        if (!$s) return null;
        try {
            return Carbon::parse($s)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /* ===================== Логіка розкладу ===================== */

    /**
     * Єдина перевірка: чи працює автобус у конкретну дату.
     * Враховує:
     *  - off_days (мають пріоритет — якщо дата в off_days, повертає false)
     *  - operation_days (whitelist, якщо has_operation_days = true)
     *  - weekly_operation_days (припускаємо, що це ["Monday", ...])
     * Якщо жодне не задано — вважаємо, що працює кожного дня.
     */
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
}
