<?php

namespace App\Console\Commands;

use App\Models\Bus;
use App\Models\Route;
use App\Models\Trip;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SimpleXMLElement;

class WpSeedCore extends Command
{
    protected $signature = 'wp:seed-core
        {--xml= : Path to WP export (WXR) XML}
        {--only= : Comma-separated wp:post_id list}
        {--dry-run : Do not write to DB, only print plan}
        {--wipe : Wipe seats/stops/trips for target buses before insert}
        {--create-missing-stops : Create missing stop names in `stops`}
        {--main-route=auto : auto|prices|direction}';

    protected $description = 'Import buses (+seats, stops, routes, trips) from WP WXR export without touching bookings.';

    /** Caches */
    private array $routeCache = []; // "start|end" => id
    private array $stopCache  = []; // "name" => id

    public function handle(): int
    {
        $xmlPath   = (string) $this->option('xml');
        $only      = $this->parseOnly($this->option('only'));
        $dry       = (bool) $this->option('dry-run');
        $wipe      = (bool) $this->option('wipe');
        $mkStops   = (bool) $this->option('create-missing-stops');
        $mainRoute = in_array($this->option('main-route'), ['auto','prices','direction'], true)
            ? $this->option('main-route') : 'auto';

        if (!$xmlPath || !is_file($xmlPath)) {
            $this->error('--xml is required and must point to a WXR file');
            return self::INVALID;
        }

        [$items, $ns] = $this->loadWxr($xmlPath);
        $countAll = 0; $countHit = 0;

        $createdBuses = $updatedBuses = 0;
        $createdRoutes = $createdTrips = 0;
        $createdSeats = $createdBusStops = 0;

        foreach ($items as $item) {
            $countAll++;

            $wp = $item->children($ns['wp']);
            if ((string)$wp->post_type !== 'wbtm_bus') continue;

            $postId = (int) $wp->post_id;
            if ($only && !in_array($postId, $only, true)) continue;
            $countHit++;

            // ------- Extract fields -------
            $title = trim((string) $item->title);
            // ВАЖЛИВО: передаємо ВЕСЬ масив $ns, а не $ns['wp']
            $meta  = $this->collectMeta($item, $ns);

            // Stops definitions
            $bpStops   = $this->maybeUnserialize($meta['wbtm_bus_bp_stops'] ?? null, true) ?: [];
            $nextStops = $this->maybeUnserialize($meta['wbtm_bus_next_stops'] ?? null, true) ?: [];
            $routeInfo = $this->maybeUnserialize($meta['wbtm_route_info'] ?? null, true) ?: [];

            // Prices (pairs & price)
            $priceRows = $this->maybeUnserialize($meta['wbtm_bus_prices'] ?? null, true) ?: [];

            // Route main direction (ordered list of names)
            $routeDirection = $this->maybeUnserialize($meta['wbtm_route_direction'] ?? null, true) ?: [];

            // Seats grid
            $gridRows  = (int) ($meta['wbtm_seat_rows'] ?? 0);
            $gridCols  = (int) ($meta['wbtm_seat_cols'] ?? 0);
            $seatsInfo = $this->maybeUnserialize($meta['wbtm_bus_seats_info'] ?? null, true) ?: [];

            // Scheduling
            $onDatesCsv = trim((string) ($meta['wbtm_bus_on_dates'] ?? ''));
            $onDates = $onDatesCsv ? array_values(array_filter(array_map('trim', explode(',', $onDatesCsv)))) : [];
            $offdayKeys = ['mon','tue','wed','thu','fri','sat','sun'];
            $offdays = [];
            foreach ($offdayKeys as $k) {
                if (($meta["offday_{$k}"] ?? '') === 'yes') $offdays[] = $k;
            }
            $startTimeHHmm = trim((string) ($meta['wbtm_bus_start_time'] ?? ''));
            $startTime = $startTimeHHmm ? $this->hhmmToTime($startTimeHHmm) : '00:00:00';

            // Seats count
            $seatsCount = (int) ($meta['wbtm_total_seat'] ?? ($meta['wbtm_get_total_seat'] ?? 0));
            if ($seatsCount <= 0) $seatsCount = $this->countSeatsFromGrid($seatsInfo);

            // Registration
            $regRaw = trim((string)($meta['wbtm_bus_no'] ?? ''));
            $registration = $this->pickRegistration($regRaw) ?: ('WP'.$postId);

            // ------- Decide main route for bus.route_id -------
            [$mainStart, $mainEnd] = $this->decideMainRoute($mainRoute, $routeDirection, $priceRows, $bpStops, $nextStops);
            $routeId = null;
            if ($mainStart && $mainEnd) {
                $routeId = $this->ensureRouteId($mainStart, $mainEnd, $dry, $createdRoutes);
            }

            // ------- Upsert bus -------
            $busData = [
                'name'                 => $title ?: "WP {$postId}",
                'seats_count'          => max($seatsCount, 0),
                'registration_number'  => $registration,
                'route_id'             => $routeId,
            ];

            // schedule
            if (!empty($onDates)) {
                $busData['schedule_type']   = 'custom';
                $busData['operation_days']      = array_map(fn($d) => ['date' => \Carbon\Carbon::parse($d)->toDateString()], $onDates);
                $busData['has_operation_days'] = 1;
                $busData['off_days']        = null;
                $busData['has_off_days']    = 0;
                $busData['weekly_operation_days'] = null;
            } else {
                $opDays = $this->weeklyOpDaysFromOffdays($offdays);
                $busData['schedule_type']   = 'weekly';
                $busData['weekly_operation_days'] = $opDays;
                $busData['has_operation_days'] = !empty($opDays) ? 1 : 0;
                $busData['off_days']        = null; // можна перенести конкретні дати із wbtm_off_dates, якщо є
                $busData['has_off_days']    = 0;
                $busData['operation_days']  = [];
            }

            if ($dry) {
                $exists = Bus::where('wp_id', $postId)->exists();
                $this->line(sprintf('[DRY-RUN][%s BUS] wp=%d name="%s" seats=%d route_id=%s',
                    $exists ? 'UPDATE' : 'CREATE',
                    $postId, $busData['name'], $busData['seats_count'], $routeId ? (string)$routeId : 'null'
                ));
                $exists ? $updatedBuses++ : $createdBuses++;
                // No children on dry-run, but print counts idea
                $this->previewChildren($postId, $seatsInfo, $routeInfo, $priceRows, $gridCols, $gridRows, $startTime);
                continue;
            }

            DB::transaction(function () use (
                $postId, $busData, $wipe, $seatsInfo, $gridCols, $gridRows, $routeInfo,
                $priceRows, $startTime, $mkStops, &$createdBuses, &$updatedBuses,
                &$createdSeats, &$createdBusStops, &$createdTrips, &$createdRoutes
            ) {
                /** @var Bus $bus */
                $bus = Bus::where('wp_id', $postId)->first();
                if ($bus) {
                    $bus->fill($busData);
                    $bus->save();
                    $updatedBuses++;
                } else {
                    $bus = new Bus();
                    $bus->wp_id = $postId;
                    foreach ($busData as $k=>$v) $bus->{$k} = $v;
                    $bus->save();
                    $createdBuses++;
                }

                if ($wipe) {
                    DB::table('bus_seats')->where('bus_id', $bus->id)->delete();
                    DB::table('bus_stops')->where('bus_id', $bus->id)->delete();
                    DB::table('trips')->where('bus_id', $bus->id)->delete();
                }

                // --- Seats from grid ---
                $createdSeats += $this->importSeatsGrid($bus->id, $seatsInfo, $gridCols, $gridRows);

                // --- Bus stops from route_info ---
                $createdBusStops += $this->importBusStops($bus->id, $routeInfo, $mkStops);

                // --- Trips from price rows ---
                $createdTrips += $this->importTrips($bus->id, $priceRows, $routeInfo, $startTime, $createdRoutes);
            });
        }

        $this->info("WXR items scanned: {$countAll}; buses matched: {$countHit}");
        $this->info("Buses → Created: {$createdBuses}, Updated: {$updatedBuses}".($dry ? ' [DRY-RUN]' : ''));
        $this->info("Seats created: {$createdSeats}".($dry ? ' [DRY-RUN]' : ''));
        $this->info("Bus stops created: {$createdBusStops}".($dry ? ' [DRY-RUN]' : ''));
        $this->info("Routes created: {$createdRoutes}".($dry ? ' [DRY-RUN]' : ''));
        $this->info("Trips created: {$createdTrips}".($dry ? ' [DRY-RUN]' : ''));

        return self::SUCCESS;
    }

    // ------------------- Helpers -------------------

    private function parseOnly($only): array
    {
        if (!$only) return [];
        return collect(explode(',', (string)$only))
            ->map(fn($x) => (int)trim($x))
            ->filter()->values()->all();
    }

    /** @return array{0:SimpleXMLElement[],1:array} */
    private function loadWxr(string $path): array
    {
        $xml = simplexml_load_file($path, 'SimpleXMLElement', LIBXML_NOCDATA);
        // Тримаємося міцніше за ns: спочатку document-level, інакше channel-level
        $ns  = $xml->getDocNamespaces(true) ?: $xml->getNamespaces(true);
        $items = $xml->channel->item ?? [];
        return [$items, $ns];
    }

    /** @return array<string,string> */
    private function collectMeta(SimpleXMLElement $item, array $ns): array
    {
        $out = [];

        // захист від нестандартних WXR без ключа 'wp'
        $wpUri = $ns['wp'] ?? 'http://wordpress.org/export/1.2/';

        $wp = $item->children($wpUri);
        if (!isset($wp->postmeta)) {
            return $out;
        }

        foreach ($wp->postmeta as $pm) {
            $k = (string)$pm->meta_key;
            $v = (string)$pm->meta_value;
            $out[$k] = $v;
        }
        return $out;
    }

    /**
     * Try to unserialize WP-serialized value; if it’s a serialized string containing serialized array, unwrap twice.
     * @param string|null $val
     * @param bool $allowArray
     * @return mixed
     */
    private function maybeUnserialize(?string $val, bool $allowArray = false)
    {
        if ($val === null || $val === '') return $allowArray ? [] : null;

        $candidate = $val;

        $attempt = function ($s) {
            // suppress warnings; WP data may have non-UTF8
            $res = @unserialize($s);
            if ($res === false && $s !== 'b:0;') return null;
            return $res;
        };

        // attempt #1
        $res = $attempt($candidate);

        // double-serialized string like: s:N:"a:..."; → unwrap
        if (is_string($res) && (Str::startsWith($res, 'a:') || Str::startsWith($res, 's:'))) {
            $res2 = $attempt($res);
            if ($res2 !== null) $res = $res2;
        }

        if ($res === null) {
            // fallback: try to decode PHP-serialized array without strict lengths (very rare)
            return $allowArray ? [] : null;
        }

        return $res;
    }

    private function hhmmToTime(string $hhmm): string
    {
        $hhmm = trim($hhmm);
        if ($hhmm === '') return '00:00:00';
        if (preg_match('/^\d{1,2}:\d{2}$/', $hhmm)) return $hhmm.':00';
        if (preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $hhmm)) return $hhmm;
        return '00:00:00';
    }

    private function pickRegistration(string $raw): ?string
    {
        if ($raw === '') return null;
        // Often like "BX 1558 HK / CA 5643 KN" → take the first token without slashes
        $first = trim(explode('/', $raw)[0]);
        return $first ?: null;
    }

    private function weeklyOpDaysFromOffdays(array $offdays): array
    {
        $days = ['mon','tue','wed','thu','fri','sat','sun'];
        $map  = ['mon'=>'Monday','tue'=>'Tuesday','wed'=>'Wednesday','thu'=>'Thursday','fri'=>'Friday','sat'=>'Saturday','sun'=>'Sunday'];
        $ops = [];
        foreach ($days as $d) if (!in_array($d, $offdays, true)) $ops[] = $map[$d];
        return $ops;
    }

    private function countSeatsFromGrid($seatsInfo): int
    {
        $c = 0;
        if (is_array($seatsInfo)) {
            foreach ($seatsInfo as $row) {
                foreach (['seat1','seat2','seat3','seat4','seat5'] as $key) {
                    if (!empty($row[$key])) $c++;
                }
            }
        }
        return $c;
    }

    private function decideMainRoute(string $mode, array $routeDirection, array $priceRows, array $bpStops, array $nextStops): array
    {
        $pick = function ($start, $end) {
            $s = is_string($start) ? trim($start) : null;
            $e = is_string($end) ? trim($end) : null;
            return [$s ?: null, $e ?: null];
        };

        if ($mode === 'direction' || ($mode === 'auto' && !empty($routeDirection))) {
            $first = $routeDirection[0] ?? null;
            $last  = $routeDirection[count($routeDirection)-1] ?? null;
            return $pick($first, $last);
        }

        if ($mode === 'prices' || ($mode === 'auto' && !empty($priceRows))) {
            $row = $priceRows[0] ?? null;
            if ($row) {
                return $pick($row['wbtm_bus_bp_price_stop'] ?? null, $row['wbtm_bus_dp_price_stop'] ?? null);
            }
        }

        // fallback: first boarding + last next stop
        if (!empty($bpStops) && !empty($nextStops)) {
            return $pick($bpStops[0] ?? null, $nextStops[count($nextStops)-1] ?? null);
        }

        return [null, null];
    }

    private function ensureRouteId(?string $start, ?string $end, bool $dry, int &$createdRoutes): ?int
    {
        if (!$start || !$end) return null;
        $key = "{$start}|{$end}";

        if (!array_key_exists($key, $this->routeCache)) {
            $id = (int) Route::query()
                ->where('start_point', $start)
                ->where('end_point', $end)
                ->value('id');
            if ($id) {
                $this->routeCache[$key] = $id;
            } else {
                if ($dry) {
                    $this->routeCache[$key] = 0;
                    $createdRoutes++;
                } else {
                    $r = Route::create(['start_point'=>$start, 'end_point'=>$end]);
                    $this->routeCache[$key] = (int)$r->id;
                    $createdRoutes++;
                }
            }
        }

        $v = $this->routeCache[$key];
        return $v ? (int)$v : null;
    }

    private function ensureStopId(string $name, bool $create): ?int
    {
        $name = trim($name);
        if ($name === '') return null;

        if (!array_key_exists($name, $this->stopCache)) {
            $id = (int) DB::table('stops')->where('name', $name)->value('id');
            if ($id) {
                $this->stopCache[$name] = $id;
            } elseif ($create) {
                $id = (int) DB::table('stops')->insertGetId([
                    'name' => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->stopCache[$name] = $id;
            } else {
                $this->stopCache[$name] = 0;
            }
        }

        $v = $this->stopCache[$name];
        return $v ? (int)$v : null;
    }

    private function importSeatsGrid(int $busId, array $seatsInfo, int $cols, int $rows): int
    {
        $made = 0;
        if (empty($seatsInfo)) return 0;

        $mapIdxToKey = fn(int $i) => ['seat1','seat2','seat3','seat4','seat5'][$i] ?? null;

        foreach ($seatsInfo as $rowIdx => $row) {
            for ($i=0; $i<5; $i++) {
                $key = $mapIdxToKey($i);
                if (!$key) continue;
                $num = isset($row[$key]) ? trim((string)$row[$key]) : '';
                if ($num === '') continue;

                $x = $i+1;               // column
                $y = $rowIdx+1;          // row

                DB::table('bus_seats')->updateOrInsert(
                    ['bus_id'=>$busId, 'number'=>$num],
                    [
                        'seat_type_id' => null,
                        'x' => $x, 'y' => $y,
                        'price_modifier_abs' => null,
                        'price_modifier_pct' => null,
                        'is_active' => 1,
                        'meta' => json_encode(['source'=>'wxr'], JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
                $made++;
            }
        }
        return $made;
    }

    private function importBusStops(int $busId, array $routeInfo, bool $mkStops): int
    {
        $made = 0;
        foreach ($routeInfo as $node) {
            $place = trim((string)($node['place'] ?? ($node['bp_point'] ?? '')));
            $time  = trim((string)($node['time'] ?? ''));
            $type  = (string)($node['type'] ?? '');
            if ($place === '' || ($type !== 'bp' && $type !== 'dp')) continue;

            $stopId = $this->ensureStopId($place, $mkStops);
            if (!$stopId) continue;

            $hhmmss = $this->hhmmToTime($time);

            DB::table('bus_stops')->updateOrInsert(
                ['bus_id'=>$busId, 'stop_id'=>$stopId, 'type'=>$type === 'bp' ? 'boarding' : 'dropping'],
                ['time'=>$hhmmss, 'updated_at'=>now(), 'created_at'=>now()]
            );
            $made++;
        }
        return $made;
    }

    private function importTrips(int $busId, array $priceRows, array $routeInfo, string $fallbackDep, int &$createdRoutes): int
    {
        $made = 0;
        if (empty($priceRows)) return 0;

        // Build time map from route_info
        $timeByPlace = [];
        foreach ($routeInfo as $node) {
            $place = trim((string)($node['place'] ?? ($node['bp_point'] ?? '')));
            $time  = trim((string)($node['time'] ?? ''));
            if ($place !== '' && $time !== '') {
                $timeByPlace[$place] = $this->hhmmToTime($time);
            }
        }

        foreach ($priceRows as $row) {
            $start = trim((string)($row['wbtm_bus_bp_price_stop'] ?? ''));
            $end   = trim((string)($row['wbtm_bus_dp_price_stop'] ?? ''));
            if ($start === '' || $end === '') continue;

            $price = (float)($row['wbtm_bus_price'] ?? 0);

            $dep = $timeByPlace[$start] ?? $fallbackDep;
            $arr = $timeByPlace[$end]   ?? '00:00:00';

            // Route ensure (also fill cache)
            $routeId = $this->ensureRouteId($start, $end, false, $createdRoutes);

            // Trip firstOrCreate
            $trip = Trip::firstOrCreate(
                [
                    'bus_id' => $busId,
                    'start_location' => $start,
                    'end_location'   => $end,
                    'departure_time' => $dep,
                    'arrival_time'   => $arr,
                ],
                ['price' => $price]
            );
            if ($trip->wasRecentlyCreated) $made++;
        }

        return $made;
    }

    private function previewChildren(int $postId, array $seatsInfo, array $routeInfo, array $priceRows, int $cols, int $rows, string $startTime): void
    {
        $this->line(sprintf('  seats: %d (grid %dx%d)', $this->countSeatsFromGrid($seatsInfo), $cols, $rows));
        $bp = 0; $dp = 0;
        foreach ($routeInfo as $n) {
            $t = (string)($n['type'] ?? '');
            if ($t === 'bp') $bp++; elseif ($t === 'dp') $dp++;
        }
        $this->line(sprintf('  bus_stops: bp=%d dp=%d', $bp, $dp));
        $this->line(sprintf('  trips to create: %d (fallback dep=%s)', count($priceRows), $startTime));
    }
}
