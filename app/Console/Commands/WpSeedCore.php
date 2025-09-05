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
        {--main-route=auto : auto|prices|direction}
        {--merge=keep : keep|update|schedule}
        {--match=wp+plate : Match strategy (wp-only|wp+plate|plate-only)}';

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
        $merge     = in_array($this->option('merge'), ['keep','update','schedule'], true) ? $this->option('merge') : 'keep';
        $mainRoute = in_array($this->option('main-route'), ['auto','prices','direction'], true)
            ? $this->option('main-route') : 'auto';
        $match     = in_array($this->option('match'), ['wp-only','wp+plate','plate-only'], true)
            ? $this->option('match') : 'wp+plate';

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

            $wp = $item->children($ns['wp'] ?? 'http://wordpress.org/export/1.2/');
            if ((string)$wp->post_type !== 'wbtm_bus') continue;

            $postId = (int) $wp->post_id;
            if ($only && !in_array($postId, $only, true)) continue;
            $countHit++;

            // ------- Extract fields -------
            $title = trim((string) $item->title);
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
            $onDates = $this->collectOnDates($meta);
            $offdays = $this->parseOffdays($meta); // array of mon..sun
            $startTimeHHmm = trim((string) ($meta['wbtm_bus_start_time'] ?? ''));
            $startTime = $this->hhmmToTime($startTimeHHmm ?: '00:00');

            // Seats count
            $seatsCount = (int) ($meta['wbtm_total_seat'] ?? ($meta['wbtm_get_total_seat'] ?? 0));
            if ($seatsCount <= 0) $seatsCount = $this->countSeatsFromGrid($seatsInfo);

            // Registration
            $regRaw = trim((string)($meta['wbtm_bus_no'] ?? ''));
            $registration = $this->normalizePlate($this->pickRegistration($regRaw)) ?: ('WP'.$postId);

            // ------- Decide main route for bus.route_id -------
            [$mainStart, $mainEnd] = $this->decideMainRoute($mainRoute, $routeDirection, $priceRows, $bpStops, $nextStops);
            $routeId = null;
            if ($mainStart && $mainEnd) {
                $routeId = $this->ensureRouteId($mainStart, $mainEnd, $dry, $createdRoutes);
            }

            // ------- Prepare bus data -------
            $busData = [
                'name'                 => $title ?: "WP {$postId}",
                'seats_count'          => max($seatsCount, 0),
                'registration_number'  => $registration,
                'route_id'             => $routeId,
            ];

            // schedule fields pre-built (used for CREATE & for merge=update)
            if (!empty($onDates)) {
                $busData['schedule_type']   = 'custom';
                $busData['operation_days']      = array_map(fn($d) => ['date' => Carbon::parse($d)->toDateString()], $onDates);
                $busData['has_operation_days'] = 1;
                $busData['off_days']        = null;
                $busData['has_off_days']    = 0;
                $busData['weekly_operation_days'] = null;
            } else {
                $opDays = $this->weeklyOpDaysFromOffdays($offdays);
                $busData['schedule_type']   = 'weekly';
                $busData['weekly_operation_days'] = $opDays;
                $busData['has_operation_days'] = !empty($opDays) ? 1 : 0;
                $busData['off_days']        = null;
                $busData['has_off_days']    = 0;
                $busData['operation_days']  = [];
            }

            // ------- Matching (configurable) -------
            $matchType = 'none';
            $existing = null;

            if ($match !== 'plate-only') {
                $existing = Bus::where('wp_id', $postId)->first();
                if ($existing) $matchType = 'wp_id';
            }
            if (!$existing && $registration && $match !== 'wp-only') {
                $existing = Bus::where('registration_number', $registration)->first();
                if ($existing) $matchType = 'plate';
            }

            // ------- DRY-RUN messages -------
            if ($dry) {
                if ($existing) {
                    if ($merge === 'keep') {
                        $this->line(sprintf(
                            '[DRY-RUN][KEEP+CHILDREN BUS] wp=%d name="%s" (match=%s) — core untouched, children upsert',
                            $postId, $existing->name, $matchType
                        ));
                        $this->previewChildren(
                            $postId, $seatsInfo, $routeInfo, $priceRows,
                            $gridCols, $gridRows, $startTime, $onDates, $offdays
                        );

                        $createdSeats    += $this->wouldCreateSeats($existing->id, $seatsInfo);
                        $createdBusStops += $this->wouldCreateBusStops($existing->id, $routeInfo, $mkStops);
                        $createdTrips    += $this->wouldCreateTrips($existing->id, $priceRows, $routeInfo, $startTime);

                        $updatedBuses++;
                        continue;
                    }
                    if ($merge === 'schedule') {
                        $this->line(sprintf('[DRY-RUN][SCHEDULE-UPDATE BUS] wp=%d name="%s" (match=%s)',
                            $postId, $existing->name, $matchType
                        ));
                        $this->previewChildren(
                            $postId, $seatsInfo, $routeInfo, $priceRows,
                            $gridCols, $gridRows, $startTime, $onDates, $offdays, true
                        );
                        $updatedBuses++;
                        continue;
                    }
                    // merge=update
                    $this->line(sprintf('[DRY-RUN][UPDATE BUS] wp=%d name="%s" seats=%d route_id=%s (match=%s)',
                        $postId, $busData['name'], $busData['seats_count'], $routeId ? (string)$routeId : 'null', $matchType
                    ));
                    $this->previewChildren($postId, $seatsInfo, $routeInfo, $priceRows, $gridCols, $gridRows, $startTime, $onDates, $offdays);
                    $updatedBuses++;
                    continue;
                } else {
                    $this->line(sprintf('[DRY-RUN][CREATE BUS] wp=%d name="%s" seats=%d route_id=%s',
                        $postId, $busData['name'], $busData['seats_count'], $routeId ? (string)$routeId : 'null'
                    ));
                    $this->previewChildren($postId, $seatsInfo, $routeInfo, $priceRows, $gridCols, $gridRows, $startTime, $onDates, $offdays);

                    // рахунок без записів у БД
                    $createdSeats    += $this->countSeatsFromGrid($seatsInfo);
                    $createdBusStops += $this->countPotentialBusStops($routeInfo, $mkStops);
                    $createdTrips    += count($priceRows);
                    $createdBuses++;
                    continue;
                }
            }

            // ------- REAL RUN -------
            DB::transaction(function () use (
                $postId, $busData, $wipe, $seatsInfo, $gridCols, $gridRows, $routeInfo,
                $priceRows, $startTime, $mkStops, $onDates, $offdays, $merge, $existing,
                &$createdBuses, &$updatedBuses, &$createdSeats, &$createdBusStops, &$createdTrips, &$createdRoutes
            ) {
                /** @var Bus|null $bus */
                $bus = $existing;
                $importChildren = true;
                $doWipe = $wipe;

                if ($bus) {
                    if ($merge === 'keep') {
                        if (!$bus->wp_id) {
                            $bus->wp_id = $postId;
                            $bus->save();
                        }
                        $doWipe = false;
                        $updatedBuses++;
                    } elseif ($merge === 'schedule') {
                        $this->applyScheduleToBus($bus, $onDates, $offdays);
                        $bus->save();
                        $updatedBuses++;
                        $importChildren = false;
                    } else { // merge=update
                        if (!$bus->wp_id) $bus->wp_id = $postId;
                        $bus->fill($busData);
                        $bus->save();
                        $updatedBuses++;
                    }
                } else {
                    // create (гарантуємо унікальність номера)
                    $bus = new Bus();
                    $bus->wp_id = $postId;
                    $busData['registration_number'] = $this->uniquifyRegistration($busData['registration_number']);
                    foreach ($busData as $k=>$v) $bus->{$k} = $v;
                    $bus->save();
                    $createdBuses++;
                }

                if ($importChildren) {
                    if ($doWipe) {
                        DB::table('bus_seats')->where('bus_id', $bus->id)->delete();
                        DB::table('bus_stops')->where('bus_id', $bus->id)->delete();
                        DB::table('trips')->where('bus_id', $bus->id)->delete();
                    }

                    $createdSeats    += $this->importSeatsGrid($bus->id, $seatsInfo, $gridCols, $gridRows);
                    $createdBusStops += $this->importBusStops($bus->id, $routeInfo, $mkStops);
                    $createdTrips    += $this->importTrips($bus->id, $priceRows, $routeInfo, $startTime, $createdRoutes);
                }
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
        $ns  = $xml->getDocNamespaces(true) ?: $xml->getNamespaces(true);
        $items = $xml->channel->item ?? [];
        return [$items, $ns];
    }

    /** @return array<string,string> */
    private function collectMeta(SimpleXMLElement $item, array $ns): array
    {
        $out = [];
        $wpUri = $ns['wp'] ?? 'http://wordpress.org/export/1.2/';
        $wp = $item->children($wpUri);
        if (!isset($wp->postmeta)) return $out;

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

    private function normalizePlate(?string $plate): ?string
    {
        if ($plate === null) return null;
        $plate = trim(preg_replace('~\s+~u', ' ', $plate));
        return $plate !== '' ? $plate : null;
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

    private function parseOffdays(array $meta): array
    {
        // Newer WP data: 'wbtm_off_days' = "monday,tuesday,..."
        $off = trim((string)($meta['wbtm_off_days'] ?? ''));
        if ($off !== '') {
            $vals = array_filter(array_map('trim', explode(',', mb_strtolower($off, 'UTF-8'))));
            $map = [
                'monday' => 'mon','tuesday'=>'tue','wednesday'=>'wed','thursday'=>'thu',
                'friday'=>'fri','saturday'=>'sat','sunday'=>'sun'
            ];
            $out = [];
            foreach ($vals as $v) if (isset($map[$v])) $out[] = $map[$v];
            return array_values(array_unique($out));
        }

        // Legacy flags: offday_mon=yes, ...
        $keys = ['mon','tue','wed','thu','fri','sat','sun'];
        $acc = [];
        foreach ($keys as $k) {
            if (isset($meta["offday_{$k}"]) && (string)$meta["offday_{$k}"] === 'yes') $acc[] = $k;
        }
        return $acc;
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

    private function previewChildren(
        int $postId,
        array $seatsInfo,
        array $routeInfo,
        array $priceRows,
        int $cols,
        int $rows,
        string $startTime,
        array $onDates = [],
        array $offdays = [],
        bool $scheduleOnly = false
    ): void {
        $this->line(sprintf('  seats: %d (grid %dx%d)', $this->countSeatsFromGrid($seatsInfo), $cols, $rows));
        $bp = 0; $dp = 0;
        foreach ($routeInfo as $n) {
            $t = (string)($n['type'] ?? '');
            if ($t === 'bp') $bp++; elseif ($t === 'dp') $dp++;
        }
        $this->line(sprintf('  bus_stops: bp=%d dp=%d', $bp, $dp));
        $this->line(sprintf('  trips to create: %d (fallback dep=%s)', count($priceRows), $startTime));

        $schedStr = !empty($onDates)
            ? ('custom('.count($onDates).' days)')
            : ('weekly (ops='.count($this->weeklyOpDaysFromOffdays($offdays)).')');
        $this->line(sprintf('  schedule: %s%s', $schedStr, $scheduleOnly ? ' [ONLY SCHEDULE WILL CHANGE]' : ''));
    }

    private function applyScheduleToBus(Bus $bus, array $onDates, array $offdays): void
    {
        if (!empty($onDates)) {
            $bus->schedule_type = 'custom';
            $bus->operation_days = array_map(
                fn($d) => ['date' => Carbon::parse($d)->toDateString()],
                $onDates
            );
            $bus->has_operation_days = 1;
            $bus->off_days = null;
            $bus->has_off_days = 0;
            $bus->weekly_operation_days = null;
        } else {
            $opDays = $this->weeklyOpDaysFromOffdays($offdays);
            $bus->schedule_type = 'weekly';
            $bus->weekly_operation_days = $opDays;
            $bus->has_operation_days = !empty($opDays) ? 1 : 0;
            $bus->off_days = null;
            $bus->has_off_days = 0;
            $bus->operation_days = [];
        }
    }

    /**
     * Build onDates from:
     * - wbtm_bus_on_dates (CSV list of YYYY-MM-DD)
     * - wbtm_particular_dates (serialized array of "MM-DD") across range [wbtm_repeated_start_date, wbtm_repeated_end_date]
     */
    private function collectOnDates(array $meta): array
    {
        $acc = [];

        // explicit dates
        $onCsv = trim((string)($meta['wbtm_bus_on_dates'] ?? ''));
        if ($onCsv !== '') {
            $parts = array_values(array_filter(array_map('trim', explode(',', $onCsv))));
            foreach ($parts as $p) {
                try {
                    $acc[] = Carbon::parse($p)->toDateString();
                } catch (\Throwable $e) {
                    // ignore bad tokens
                }
            }
        }

        // particular (MM-DD) within a repeated range
        $mmdd = $this->maybeUnserialize($meta['wbtm_particular_dates'] ?? null, true) ?: [];
        $start = trim((string)($meta['wbtm_repeated_start_date'] ?? ''));
        $end   = trim((string)($meta['wbtm_repeated_end_date'] ?? ''));
        if (!empty($mmdd) && $start !== '' && $end !== '') {
            try {
                $acc = array_merge($acc, $this->expandParticularDates($mmdd, $start, $end));
            } catch (\Throwable $e) {
                // ignore range errors
            }
        }

        // dedupe + sort
        $acc = array_values(array_unique($acc));
        sort($acc);
        return $acc;
    }

    /**
     * Expand list of "MM-DD" into full Y-m-d dates within [start,end] inclusive.
     * @param array<int,string> $mmddList
     * @return array<int,string> YYYY-MM-DD
     */
    private function expandParticularDates(array $mmddList, string $startDate, string $endDate): array
    {
        $wanted = [];
        foreach ($mmddList as $v) {
            $v = trim((string)$v);
            if ($v === '') continue;
            if (!preg_match('/^\d{1,2}-\d{1,2}$/', $v)) continue;
            [$m,$d] = array_map('intval', explode('-', $v));
            if ($m < 1 || $m > 12 || $d < 1 || $d > 31) continue;
            $wanted[sprintf('%02d-%02d', $m, $d)] = true;
        }
        if (empty($wanted)) return [];

        $start = Carbon::parse($startDate)->startOfDay();
        $end   = Carbon::parse($endDate)->endOfDay();
        if ($end->lt($start)) return [];

        $out = [];
        for ($cur = $start->copy(); $cur->lte($end); $cur->addDay()) {
            $key = $cur->format('m-d');
            if (isset($wanted[$key])) {
                $out[] = $cur->toDateString();
            }
        }
        return $out;
    }

    private function wouldCreateSeats(int $busId, array $seatsInfo): int
    {
        if (empty($seatsInfo)) return 0;
        $want = [];
        foreach ($seatsInfo as $row) {
            foreach (['seat1','seat2','seat3','seat4','seat5'] as $k) {
                $n = isset($row[$k]) ? trim((string)$row[$k]) : '';
                if ($n !== '') $want[$n] = true;
            }
        }
        if (empty($want)) return 0;
        $have = DB::table('bus_seats')->where('bus_id',$busId)->pluck('number')->all();
        $haveSet = [];
        foreach ($have as $n) $haveSet[(string)$n] = true;
        $new = 0;
        foreach ($want as $n => $_) if (!isset($haveSet[$n])) $new++;
        return $new;
    }

    private function wouldCreateBusStops(int $busId, array $routeInfo, bool $mkStops): int
    {
        if (empty($routeInfo)) return 0;
        $new = 0;
        foreach ($routeInfo as $node) {
            $place = trim((string)($node['place'] ?? ($node['bp_point'] ?? '')));
            $type  = (string)($node['type'] ?? '');
            if ($place === '' || ($type !== 'bp' && $type !== 'dp')) continue;

            $stopId = $this->ensureStopId($place, false);
            if (!$stopId && !$mkStops) continue;
            if (!$stopId && $mkStops) { $new++; continue; }

            $exists = DB::table('bus_stops')->where([
                'bus_id' => $busId,
                'stop_id'=> $stopId,
                'type'   => $type === 'bp' ? 'boarding' : 'dropping',
            ])->exists();
            if (!$exists) $new++;
        }
        return $new;
    }

    private function wouldCreateTrips(int $busId, array $priceRows, array $routeInfo, string $fallbackDep): int
    {
        if (empty($priceRows)) return 0;

        $timeByPlace = [];
        foreach ($routeInfo as $node) {
            $p = trim((string)($node['place'] ?? ($node['bp_point'] ?? '')));
            $t = trim((string)($node['time'] ?? ''));
            if ($p !== '' && $t !== '') $timeByPlace[$p] = $this->hhmmToTime($t);
        }

        $new = 0;
        foreach ($priceRows as $row) {
            $start = trim((string)($row['wbtm_bus_bp_price_stop'] ?? ''));
            $end   = trim((string)($row['wbtm_bus_dp_price_stop'] ?? ''));
            if ($start === '' || $end === '') continue;

            $dep = $timeByPlace[$start] ?? $fallbackDep;
            $arr = $timeByPlace[$end]   ?? '00:00:00';

            $exists = DB::table('trips')->where([
                'bus_id'          => $busId,
                'start_location'  => $start,
                'end_location'    => $end,
                'departure_time'  => $dep,
                'arrival_time'    => $arr,
            ])->exists();

            if (!$exists) $new++;
        }
        return $new;
    }

    /** Count potential bus_stops for DRY-RUN create path without DB writes */
    private function countPotentialBusStops(array $routeInfo, bool $mkStops): int
    {
        $cnt = 0;
        foreach ($routeInfo as $node) {
            $place = trim((string)($node['place'] ?? ($node['bp_point'] ?? '')));
            $type  = (string)($node['type'] ?? '');
            if ($place === '' || ($type !== 'bp' && $type !== 'dp')) continue;
            // якщо дозволено створювати зупинки — рахуємо; якщо ні — рахуємо лише умовно існуючі (не перевіряємо БД у DRY-RUN)
            $cnt++;
        }
        return $cnt;
    }

    /** Ensure registration_number is unique by appending suffixes if needed */
    private function uniquifyRegistration(?string $reg): ?string
    {
        if ($reg === null || $reg === '') return $reg;
        $base = $reg; $i = 2; $cur = $reg;
        while (DB::table('buses')->where('registration_number', $cur)->exists()) {
            $cur = $base.' #'.$i++;
        }
        return $cur;
    }

}
