<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Bus;
use App\Models\Route;
use App\Models\Trip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PromoteWPBookings extends Command
{
    protected $signature = 'wp:promote-bookings
        {--dry-run : Не записувати у БД, лише показувати план дій}
        {--chunk=500 : Розмір чанку}
        {--since= : Тільки записи зі start_time >= YYYY-MM-DD}
        {--only= : Кома-сепарейтед список WP booking IDs}
        {--no-create-users : Не створювати відсутніх користувачів}
        {--no-create-buses : Не створювати відсутні автобуси (лише лінкувати по buses.wp_id; інакше пропуск)}
        {--no-create-routes : Не створювати відсутні маршрути}
        {--no-create-trips : Не створювати відсутні рейси (trips)}
    ';

    protected $description = 'Підвищення (promote) з wp_bookings_clean у routes/buses/trips/bookings з ідемпотентністю та розв’язанням колізій місць.';

    public function handle(): int
    {
        $dry = (bool)$this->option('dry-run');
        $chunk = max((int)$this->option('chunk'), 50);
        $since = $this->option('since');
        $only = $this->option('only');
        $createUsers = !$this->option('no-create-users');
        $createBuses = !$this->option('no-create-buses');
        $createRoutes = !$this->option('no-create-routes');
        $createTrips = !$this->option('no-create-trips');

        // Джерело — view / таблиця wp_bookings_clean
        $src = DB::table('wp_bookings_clean')
            ->when($since, fn($q) => $q->whereDate('start_time', '>=', $since))
            ->when($only, function ($q) use ($only) {
                $ids = collect(explode(',', $only))->map(fn($x) => (int)trim($x))->filter()->values();
                return $q->whereIn('wp_id', $ids);
            })
            ->orderBy('id');

        $total = (clone $src)->count();
        $this->info("wp_bookings_clean rows: {$total}");
        if ($total === 0) {
            return self::SUCCESS;
        }

        // Для seats_count при автостворенні Bus
        $maxSeatByWpBus = DB::table('wp_bookings_clean')
            ->select('wp_bus_id', DB::raw('MAX(CAST(seat_number AS UNSIGNED)) AS max_seat'))
            ->whereNotNull('wp_bus_id')
            ->groupBy('wp_bus_id')
            ->pluck('max_seat', 'wp_bus_id');

        // Кеші
        $routeCache = []; // "{$start}|{$end}" => id або 0 у DRY
        $busCache = []; // "wp:{wp_id}" або "r:{routeId}" => id, 0 у DRY або null коли створення заборонене
        $tripCache = []; // "busId|dep|arr|start|end" => id або 0 у DRY
        $userCache = []; // "e:{email}" або "p:{phone}" => user_id

        $statusMap = [
            'completed' => 'paid',
            'processing' => 'paid',
            'pending' => 'pending',
            'on-hold' => 'pending',
            'failed' => 'cancelled',
            'cancelled' => 'cancelled',
            'refunded' => 'refunded',
        ];

        $createdRoutes = $createdBuses = $createdTrips = 0;
        $createdB = $updatedB = $skippedB = 0;

        $src->chunk($chunk, function ($rows) use (
            $dry, $createUsers, $createBuses, $createRoutes, $createTrips, $maxSeatByWpBus,
            &$routeCache, &$busCache, &$tripCache, &$userCache,
            &$createdRoutes, &$createdBuses, &$createdTrips,
            $statusMap, &$createdB, &$updatedB, &$skippedB
        ) {
            foreach ($rows as $r) {
                $wpId = (int)$r->wp_id;
                $wpBus = $r->wp_bus_id ? (int)$r->wp_bus_id : null;

                $seat = $r->seat_number !== null ? (int)$r->seat_number : null;
                $start = $r->start_time ? Carbon::parse($r->start_time) : null;
                $drop = $r->dropping_time ? Carbon::parse($r->dropping_time) : null;
                $date = $start?->toDateString();

                if (!$date || !$seat || $seat <= 0) {
                    $skippedB++;
                    $this->line("[SKIP] wp={$wpId} – відсутня дата або seat_number");
                    continue;
                }

                $boarding = trim((string)($r->boarding_point ?? ''));
                $dropping = trim((string)($r->dropping_point ?? ''));
                $depTime = $start->format('H:i:s');
                $arrTime = $drop ? $drop->format('H:i:s') : '00:00:00';

                // 1) ROUTE
                $routeId = $this->ensureRouteId($boarding, $dropping, $dry, $createRoutes, $routeCache, $createdRoutes);
                // 2) BUS
                $busName = $wpBus
                    ? "WP {$wpBus}: {$boarding} → {$dropping}"
                    : "WP: {$boarding} → {$dropping}";
                $busId = $this->ensureBusId($wpBus, $routeId, $busName, $dry, $busCache, $maxSeatByWpBus, $createdBuses, $createBuses);

                // ВАЖЛИВО: у DRY повертається 0 як плейсхолдер — це ОК; пропускати треба тільки коли === null
                if ($busId === null) {
                    $skippedB++;
                    $this->line("[SKIP] wp={$wpId} – bus не знайдено і створення заборонене (--no-create-buses)");
                    continue;
                }

                // 3) TRIP
                $priceForTrip = (float)($r->fare ?? $r->total_price ?? 0);
                $tripId = $this->ensureTripId(
                    $busId ?: 0,
                    $boarding, $dropping, $depTime, $arrTime,
                    $priceForTrip, $dry, $createTrips, $tripCache, $createdTrips
                );

// ⬇️ ДОДАТИ ОЦЕ:
                if ($tripId === null) {
                    $skippedB++;
                    $this->line("[SKIP] wp={$wpId} – trip не знайдено і створення заборонене (--no-create-trips)");
                    continue;
                }

                // 4) USER
                $email = $this->normEmail($r->passenger_email_effective ?: $r->passenger_email);
                $phone = $this->normPhone($r->passenger_phone);
                [$first, $last] = $this->splitPassengerName($r->passenger_name);

                $userId = null;
                if ($email && array_key_exists('e:' . $email, $userCache)) {
                    $userId = $userCache['e:' . $email];
                }
                if (!$userId && $phone && array_key_exists('p:' . $phone, $userCache)) {
                    $userId = $userCache['p:' . $phone];
                }

                if (!$userId) {
                    $u = null;
                    if ($email) $u = User::where('email', $email)->first();
                    if (!$u && $phone) $u = User::where('phone', $phone)->first();

                    // створюємо юзера тільки якщо є email АБО телефон
                    if (!$u && $createUsers && !$dry && ($email || $phone)) {
                        try {
                            $u = User::create([
                                'name' => $first ?: ($email ? explode('@', $email)[0] : 'user_' . Str::random(6)),
                                'surname' => $last,
                                'email' => $email,          // може бути null, якщо схема це дозволяє
                                'phone' => $phone,
                                'password' => bcrypt(Str::random(16)),
                                'wp_password' => null,            // важливо, якщо у схемі був NOT NULL
                            ]);
                        } catch (\Throwable $e) {
                            // Якщо у схемі email NOT NULL — підставимо синтетичний, але унікальний
                            if (str_contains($e->getMessage(), "Column 'email' cannot be null")) {
                                $u = User::create([
                                    'name' => $first ?: 'user_' . Str::random(6),
                                    'surname' => $last,
                                    'email' => 'wp-' . $wpId . '@import.local',
                                    'phone' => $phone,
                                    'password' => bcrypt(Str::random(16)),
                                    'wp_password' => null,
                                ]);
                            } else {
                                throw $e;
                            }
                        }
                    }

                    if ($u) {
                        if ($email) $userCache['e:' . $email] = $u->id;
                        if ($phone) $userCache['p:' . $phone] = $u->id;
                        $userId = $u->id;
                    }
                }

                // 5) Passenger payload
                $passenger = [[
                    'first_name' => $first,
                    'last_name' => $last,
                    'doc_number' => null,
                    'category' => 'adult',
                    'email' => $email ?: null,
                    'phone_number' => $phone ?: null,
                    'note' => null,
                ]];

                // 6) статус/ціни/ідемпотентність
                $status = $statusMap[$r->order_status] ?? 'pending';
                $priceUAH = (float)($r->total_price ?? $r->fare ?? 0);
                $invoice = 'wp:' . $wpId;
                $orderId = $r->wp_order_id ? ('wp-order-' . $r->wp_order_id) : ('wp-booking-' . $wpId);

                $data = [
                    'route_id' => $routeId ?: null,
                    'trip_id' => $tripId ?: null,
                    'bus_id' => $busId ?: null, // у DRY тут 0 — при реальному запуску буде int id
                    'date' => $date,
                    'selected_seat' => $seat,
                    'seat_number' => $seat,
                    'user_id' => $userId,
                    'status' => $status,
                    'order_id' => $orderId,
                    'currency_code' => 'UAH',
                    'fx_rate' => 1.0,
                    'price' => $priceUAH,
                    'price_uah' => $priceUAH,
                    'discount_amount' => 0,
                    'promo_code' => null,
                    'passengers' => $passenger,
                    'additional_services' => null,
                    'pricing' => [
                        'seat_uah' => (float)($r->fare ?? 0),
                        'extras_uah' => 0,
                    ],
                    'payment_method' => $r->payment_method ?: null,
                    'invoice_number' => $invoice,
                    'payment_meta' => [
                        'wp_id' => (int)$wpId,
                        'wp_bus_id' => $wpBus,
                        'wp_order_id' => $r->wp_order_id,
                        'booking_date' => $r->booking_date,
                    ],
                ];

                // 7) Розв’язання колізій місць (bus_id+date+seat)
                $priorityOf = fn(string $st) => match ($st) {
                    'paid' => 3,
                    'pending' => 2,
                    'refunded' => 1,
                    'cancelled' => 0,
                    default => 1,
                };
                $incomingPriority = $priorityOf($status);

                if ($dry) {
                    // Симуляція
                    $conflicts = Booking::query()
                        ->where('bus_id', '>=', 1) // у DRY bus_id може бути 0, тож конфліктів у БД не знайдемо — це нормально
                        ->where('bus_id', $busId ?: -1)
                        ->whereDate('date', $date)
                        ->where('seat_number', $seat)
                        ->where('invoice_number', '<>', $invoice)
                        ->get();

                    $winnerIsIncoming = true;
                    foreach ($conflicts as $c) {
                        if ($priorityOf($c->status) > $incomingPriority) {
                            $winnerIsIncoming = false;
                            break;
                        }
                    }

                    if (!$winnerIsIncoming) {
                        $data['status'] = 'cancelled';
                        $data['payment_meta']['import_note'] = 'collision_loser';
                        $this->line(sprintf(
                            '[DRY-RUN][COLLISION→LOSE] wp=%d seat=%d %s bus=%s',
                            $wpId, $seat, $date, $busId ?: 'DRY'
                        ));
                    } elseif ($conflicts->isNotEmpty()) {
                        $this->line(sprintf(
                            '[DRY-RUN][COLLISION→WIN] wp=%d seat=%d %s bus=%s; буде скасовано дублів: %d',
                            $wpId, $seat, $date, $busId ?: 'DRY', $conflicts->count()
                        ));
                    }

                    $exists = Booking::where('invoice_number', $invoice)->exists();
                    $this->line(sprintf(
                        '[DRY-RUN][%s] wp=%d seat=%d %s bus=%s route=%s price=%.2f status=%s',
                        $exists ? 'UPDATE' : 'CREATE',
                        $wpId, $seat, $date, $busId ?: 'DRY', (int)$routeId, $priceUAH, $data['status']
                    ));
                    $exists ? $updatedB++ : $createdB++;
                    continue;
                }

                // Реальний режим: транзакційно колізії + upsert
                DB::transaction(function () use (
                    $busId, $date, $seat, $invoice, $incomingPriority,
                    $priorityOf, &$data, &$createdB, &$updatedB
                ) {
                    $conflicts = Booking::query()
                        ->where('bus_id', $busId)
                        ->whereDate('date', $date)
                        ->where('seat_number', $seat)
                        ->where('invoice_number', '<>', $invoice)
                        ->lockForUpdate()
                        ->get();

                    $winnerIsIncoming = true;
                    foreach ($conflicts as $c) {
                        if ($priorityOf($c->status) > $incomingPriority) {
                            $winnerIsIncoming = false;
                            break;
                        }
                    }

                    if (!$winnerIsIncoming) {
                        $pm = is_array($data['payment_meta']) ? $data['payment_meta'] : [];
                        $pm['import_note'] = 'collision_loser';
                        $pm['collision_with'] = $conflicts->pluck('invoice_number')->values();
                        $data['payment_meta'] = $pm;
                        $data['status'] = 'cancelled';
                    } else {
                        foreach ($conflicts as $loser) {
                            $loser->update([
                                'status' => 'cancelled',
                                'payment_meta' => array_merge(
                                    is_array($loser->payment_meta) ? $loser->payment_meta : [],
                                    ['import_note' => 'collision_cancelled_by:' . $invoice]
                                ),
                            ]);
                        }
                    }

                    $b = Booking::updateOrCreate(['invoice_number' => $invoice], $data);
                    $b->wasRecentlyCreated ? $createdB++ : $updatedB++;
                });
            }
        });

        $this->info("Routes created: {$createdRoutes}; Buses created: {$createdBuses}; Trips created: {$createdTrips}");
        $this->info("Bookings → Created: {$createdB}, Updated: {$updatedB}, Skipped: {$skippedB}" . ($dry ? ' [DRY-RUN]' : ''));

        return self::SUCCESS;
    }

    // ---------- Helpers ----------

    private function ensureRouteId(string $start, string $end, bool $dry, bool $createRoutes,
                                   array &$routeCache, int &$createdRoutes): ?int
    {
        $key = "{$start}|{$end}";
        if (!array_key_exists($key, $routeCache)) {
            $foundId = (int) Route::query()
                ->where('start_point', $start)
                ->where('end_point', $end)
                ->value('id');

            if ($foundId) { $routeCache[$key] = $foundId; return $foundId; }

            // ⬇️ якщо створювати не можна — повертаємо null навіть у DRY
            if (!$createRoutes) { $routeCache[$key] = null; return null; }

            if ($dry) { $routeCache[$key] = 0; $createdRoutes++; return 0; }

            $route = Route::create(['start_point' => $start, 'end_point' => $end]);
            $routeCache[$key] = (int) $route->id;
            $createdRoutes++;
        }
        return $routeCache[$key] === 0 ? 0 : ($routeCache[$key] ?: null);
    }

    /**
     * Повертає:
     *  - int id   — реальний id автобуса
     *  - 0        — у DRY-режимі «було б створено» (НЕ вважається пропуском)
     *  - null     — створення заборонене (--no-create-buses) і не знайдено
     */
    private function ensureBusId(?int $wpBus, ?int $routeId, string $name, bool $dry, array &$busCache, \Illuminate\Support\Collection $maxSeatByWpBus, int &$createdBuses, bool $createBuses): ?int
    {
        $key = $wpBus ? "wp:{$wpBus}" : ('r:' . ($routeId ?? 0));

        if (!array_key_exists($key, $busCache)) {
            // Спробуємо знайти
            $q = Bus::query();
            if ($wpBus) {
                $q->where('wp_id', $wpBus);
            } else {
                $q->whereNull('wp_id')
                    ->when($routeId === null,
                        fn($qq) => $qq->whereNull('route_id'),
                        fn($qq) => $qq->where('route_id', $routeId)
                    );
            }
            $foundId = (int)$q->value('id');

            if ($foundId) {
                $busCache[$key] = $foundId;
                return $foundId;
            }

            if ($dry) {
                $busCache[$key] = 0; // симуляція створення
                $createdBuses++;
                return 0;
            }

            if (!$createBuses) {
                $busCache[$key] = null;
                return null;
            }

            if (!$wpBus && $routeId === null) {
                $busCache[$key] = null;
                return null;
            }

            // Створюємо
            $bus = new Bus();
            $bus->wp_id = $wpBus;
            $bus->name = $name;
            $bus->registration_number = $wpBus ? "WP{$wpBus}" : "WP-{$routeId}";
            $bus->seats_count = (int)($wpBus ? ($maxSeatByWpBus[$wpBus] ?? 50) : 50);
            $bus->route_id = $routeId ?: null;
            $bus->schedule_type = 'weekly';
            $bus->save();

            $busCache[$key] = (int)$bus->id;
            $createdBuses++;
        }

        return $busCache[$key]; // може бути int або 0 або null
    }

    private function ensureTripId(int $busId, string $start, string $end, string $dep, string $arr,
                                  float $price, bool $dry, bool $createTrips,
                                  array &$tripCache, int &$createdTrips): ?int
    {
        $key = "{$busId}|{$dep}|{$arr}|{$start}|{$end}";
        if (!array_key_exists($key, $tripCache)) {
            $foundId = (int) Trip::query()
                ->where('bus_id', $busId ?: -1)
                ->where('start_location', $start)
                ->where('end_location', $end)
                ->where('departure_time', $dep)
                ->where('arrival_time', $arr)
                ->value('id');

            if ($foundId) { $tripCache[$key] = $foundId; return $foundId; }

            // ⬇️ як і вище — поважаємо no-create
            if (!$createTrips) { $tripCache[$key] = null; return null; }

            if ($dry) { $tripCache[$key] = 0; $createdTrips++; return 0; }

            $trip = Trip::firstOrCreate(
                ['bus_id' => $busId, 'start_location' => $start, 'end_location' => $end, 'departure_time' => $dep, 'arrival_time' => $arr],
                ['price' => $price]
            );
            $tripCache[$key] = (int) $trip->id;
            if ($trip->wasRecentlyCreated) $createdTrips++;
        }
        return $tripCache[$key] === 0 ? 0 : ($tripCache[$key] ?: null);
    }

    private function normEmail($e): ?string
    {
        $e = is_string($e) ? trim($e) : null;
        return $e ? mb_strtolower($e, 'UTF-8') : null;
    }

    private function normPhone($p): ?string
    {
        if (!$p) return null;
        $digits = preg_replace('/\D+/', '', (string)$p);
        if ($digits === '') return null;
        return str_starts_with((string)$p, '+') ? '+' . $digits : $digits;
    }

    /**
     * У WP поле часто у форматі "Прізвище Імʼя" — беремо останнє слово як імʼя, решту як прізвище.
     */
    private function splitPassengerName($full): array
    {
        $t = trim((string)$full);
        if ($t === '') return [null, null];
        $parts = preg_split('/\s+/u', $t);
        if (count($parts) === 1) return [$parts[0], null];
        $firstName = array_pop($parts);            // останнє — імʼя
        $lastName = implode(' ', $parts) ?: null; // решта — прізвище
        return [$firstName, $lastName];
    }
}
