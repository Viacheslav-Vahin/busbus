<?php
// app/Console/Commands/ImportWPBookings.php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\WP as WpUtil;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ImportWPBookings extends Command
{
    protected $signature = 'wp:import-bookings
        {--dry-run : Лише показати, що буде створено/оновлено}
        {--chunk=500 : Розмір чанку}
        {--since= : Імпортувати бронювання, створені після цієї дати (YYYY-MM-DD)}
        {--link-buses : Спробувати підв’язати bus_id по buses.wp_id}
    ';

    protected $description = 'Імпорт wbtm_bus_booking з WordPress у стаджингову таблицю wp_bookings_raw.';

    public function handle(): int
    {
        $dry       = (bool)$this->option('dry-run');
        $chunk     = (int)$this->option('chunk') ?: 500;
        $since     = $this->option('since');
        $linkBuses = (bool)$this->option('link-buses');

        $posts = 'posts';
        $meta  = 'postmeta';

        // Скільки всього
        $q = DB::connection('wordpress')->table($posts)
            ->where('post_type', 'wbtm_bus_booking');

        if ($since) {
            $q->where('post_date', '>=', $since.' 00:00:00');
        }

        $total = $q->count();
        $this->info("Found {$total} wbtm_bus_booking".($since ? " since {$since}" : ''));

        $created = $updated = $skipped = 0;

        $q->orderBy('ID')->chunk($chunk, function ($rows) use ($dry, $posts, $meta, &$created, &$updated, &$skipped, $linkBuses) {
            $ids = collect($rows)->pluck('ID')->all();

            // Метадані всіх постів чанку
            $metas = DB::connection('wordpress')->table($meta)
                ->whereIn('post_id', $ids)
                ->select('post_id','meta_key','meta_value')
                ->get()
                ->groupBy('post_id');

            // Підтягнути метадату пов'язаних Woo Orders (оплата/сума/білінг)
            $orderIds = [];
            foreach ($rows as $p) {
                $ms = $metas->get($p->ID, collect());
                $orderId = optional($ms->firstWhere('meta_key', 'wbtm_order_id'))->meta_value ?? null;
                if ($orderId) $orderIds[] = (int)$orderId;
            }
            $orderIds = array_values(array_unique($orderIds));
            $orders = [];
            if (!empty($orderIds)) {
                $orderMeta = DB::connection('wordpress')->table($meta)
                    ->whereIn('post_id', $orderIds)
                    ->whereIn('meta_key', [
                        '_payment_method','_payment_method_title',
                        '_order_total','_order_currency',
                        '_billing_email','_billing_phone'
                    ])
                    ->select('post_id','meta_key','meta_value')
                    ->get()
                    ->groupBy('post_id');

                foreach ($orderMeta as $oid => $list) {
                    $row = [];
                    foreach ($list as $m) $row[$m->meta_key] = $m->meta_value;
                    $orders[$oid] = $row;
                }
            }

            // (опціонально) карта wp_bus_id -> наш buses.id
            $busMap = [];
            if ($linkBuses) {
                $wpBusIds = [];
                foreach ($rows as $p) {
                    $ms = $metas->get($p->ID, collect());
                    $wpb = optional($ms->firstWhere('meta_key','wbtm_bus_id'))->meta_value ?? null;
                    if ($wpb) $wpBusIds[] = (int)$wpb;
                }
                $wpBusIds = array_values(array_unique($wpBusIds));
                if (!empty($wpBusIds)) {
                    // потрібна наявність стовпця buses.wp_id
                    $busMap = DB::table('buses')->whereIn('wp_id', $wpBusIds)->pluck('id','wp_id')->all();
                }
            }

            // список емейлів менеджерів: з конфігу + дефолти
            $managerEmailsCfg = array_map('strtolower', config('wp.manager_emails', []));
            $managerEmailsDef = [
                'maxbus2211@gmail.com','maxbus601@gmail.com','maxbusck@gmail.com',
                'cisarkostya92@gmail.com','fedorenko.olya.v@gmail.com','iji@tat.ua',
                'mangolundovskaya@gmail.com','meduza.visa.travel@gmail.com','0112199277@ukr.net',
                'sale@viva-tour.net.ua'
            ];
            $managerEmails = array_values(array_unique(array_merge($managerEmailsCfg, $managerEmailsDef)));

            foreach ($rows as $post) {
                $ms = $metas->get($post->ID, collect())->keyBy('meta_key');

                // Ключі
                $wp_order_id = (int)($ms['wbtm_order_id']->meta_value ?? 0) ?: null;
                $wp_bus_id   = (int)($ms['wbtm_bus_id']->meta_value   ?? 0) ?: null;

                $boarding_pt = $ms['wbtm_boarding_point']->meta_value ?? null;
                $boarding_tm = WpUtil::toDateTime($ms['wbtm_boarding_time']->meta_value ?? null);
                $dropping_pt = $ms['wbtm_dropping_point']->meta_value ?? null;
                $dropping_tm = WpUtil::toDateTime($ms['wbtm_dropping_time']->meta_value ?? null);
                $start_tm    = WpUtil::toDateTime($ms['wbtm_start_time']->meta_value ?? null);
                $booking_dt  = WpUtil::toDateTime($ms['wbtm_booking_date']->meta_value ?? null);

                $ticket_type = $ms['wbtm_ticket']->meta_value ?? null;
                $seat        = $ms['wbtm_seat']->meta_value ?? null;
                $fare        = isset($ms['wbtm_bus_fare']) ? (float)$ms['wbtm_bus_fare']->meta_value : null;
                $total_price = isset($ms['wbtm_tp']) ? (float)$ms['wbtm_tp']->meta_value : null;

                $order_status = $ms['wbtm_order_status']->meta_value ?? null;
                $ticket_status_raw = $ms['wbtm_ticket_status']->meta_value ?? null;
                $ticket_status = $ticket_status_raw === '1' ? 'active' : ($ticket_status_raw === '0' ? 'inactive' : $ticket_status_raw);

                $payment_method = $ms['wbtm_billing_type']->meta_value ?? null;

                // Хто оформляв (booked_by)
                $booked_by_name_raw  = $ms['wbtm_user_name']->meta_value  ?? null;
                $booked_by_email_raw = $ms['wbtm_user_email']->meta_value ?? null;
                $booked_by_phone_raw = $ms['wbtm_user_phone']->meta_value ?? null;

                // Пасажир (із attendee_info; якщо порожньо — fallback до booked_by_*)
                $passenger_name  = null;
                $passenger_email = null;
                $passenger_phone = null;

                $attendee_raw = $ms['wbtm_attendee_info']->meta_value ?? null;
                $attendee     = WpUtil::maybeUnserialize($attendee_raw);

                // збережемо email з attendee окремо (для “підозрілого” кейсу)
                $attendee_email = null;

                if (is_array($attendee)) {
                    $passenger_name  = $attendee['wbtm_full_name']['value'] ?? null;
                    $passenger_email = $attendee['wbtm_reg_email']['value'] ?? null;
                    $passenger_phone = $attendee['wbtm_reg_phone']['value'] ?? null;
                    $attendee_email  = $passenger_email ?: null;
                }

                if (!$passenger_name)  $passenger_name  = $booked_by_name_raw  ?? null;
                if (!$passenger_email) $passenger_email = $booked_by_email_raw ?? null;
                if (!$passenger_phone) $passenger_phone = $booked_by_phone_raw ?? null;

                // Дотягуємо з Woo order (білінг/сума/метод)
                if ($wp_order_id && isset($orders[$wp_order_id])) {
                    $om = $orders[$wp_order_id];
                    $payment_method  = $payment_method ?: ($om['_payment_method'] ?? null);
                    $total_price     = $total_price   ?? (isset($om['_order_total']) ? (float)$om['_order_total'] : null);
                    $passenger_email = $passenger_email ?: ($om['_billing_email'] ?? null);
                    $passenger_phone = $passenger_phone ?: ($om['_billing_phone'] ?? null);
                }

                // Нормалізація телефонів (пасажир)
                $phonesPassenger = $this->extractPhones($passenger_phone);
                [$passenger_phone, $passenger_extra_phones] = $this->pickPrimaryPhone($phonesPassenger);
                if (!$passenger_phone && $passenger_phone !== null) {
                    $passenger_phone = WpUtil::cleanPhone($passenger_phone); // запасний варіант
                }

                // Нормалізація телефонів (хто оформляв)
                $phonesBookedBy = $this->extractPhones($booked_by_phone_raw);
                [$booked_by_phone] = $this->pickPrimaryPhone($phonesBookedBy);
                if (!$booked_by_phone && $booked_by_phone_raw !== null) {
                    $booked_by_phone = WpUtil::cleanPhone($booked_by_phone_raw);
                }

                // --- Менеджер? / Третя особа? (оновлена логіка) ---
                $booked_by_name  = $booked_by_name_raw ? trim($booked_by_name_raw) : null;
                $booked_by_email = $booked_by_email_raw ? trim($booked_by_email_raw) : null;

                // нормалізовані значення
                $pbEmail = $this->normEmail($passenger_email);
                $bbEmail = $this->normEmail($booked_by_email);

                $pbPhone = $passenger_phone ?: null;  // вже нормалізовано
                $bbPhone = $booked_by_phone ?: null;  // вже нормалізовано

                $pbName  = $this->normName($passenger_name);
                $bbName  = $this->normName($booked_by_name);

                // чи це та сама людина?
                $isSamePerson =
                    ($pbEmail && $bbEmail && $pbEmail === $bbEmail)
                    || ($pbPhone && $bbPhone && $pbPhone === $bbPhone)
                    || (
                        $pbName && $bbName && $pbName === $bbName &&
                        (
                            ($pbEmail && $bbEmail && $pbEmail === $bbEmail) ||
                            ($pbPhone && $bbPhone && $pbPhone === $bbPhone)
                        )
                    );

                // суворий прапор менеджера
                $booked_by_is_manager = false;
                if ($bbEmail && in_array($bbEmail, $managerEmails, true)) {
                    $booked_by_is_manager = true;
                } elseif (Str::contains($bbName ?? '', ['менеджер','manager'])) {
                    $booked_by_is_manager = true;
                }

                // окремий прапор "третя особа"
                $booked_by_is_third_party = $booked_by_is_manager ? true : !$isSamePerson;

                // === НОВЕ ПРАВИЛКО: підозрілий email пасажира ===
                // Якщо бронює менеджер і passenger_email ∈ managerEmails —
                // підміняємо на email з attendee_info (якщо був), інакше ставимо null.
                $peNorm = $this->normEmail($passenger_email);
                if ($booked_by_is_manager && $peNorm && in_array($peNorm, $managerEmails, true)) {
                    $passenger_email = $attendee_email ?: null;
                }

                // Шукаймо користувача ТІЛЬКИ за даними пасажира
                $userId = null;
                if ($passenger_email) {
                    $u = User::query()->where('email', $passenger_email)->first();
                    if ($u) $userId = $u->id;
                }
                if (!$userId && $passenger_phone) {
                    $u = User::query()->where('phone', $passenger_phone)->first();
                    if ($u) $userId = $u->id;
                }

                // Хто оформляв — локальний користувач (якщо є)
                $booked_by_user_id = null;
                if ($booked_by_email) {
                    $bu = User::query()->where('email', $booked_by_email)->first();
                    if ($bu) $booked_by_user_id = $bu->id;
                }
                if (!$booked_by_user_id && $booked_by_phone) {
                    $bu = User::query()->where('phone', $booked_by_phone)->first();
                    if ($bu) $booked_by_user_id = $bu->id;
                }

                // Мапа автобусів
                $busId = null;
                if ($wp_bus_id && !empty($busMap)) {
                    $busId = $busMap[$wp_bus_id] ?? null;
                }

                // Чи існує вже у стаджингу
                $exists = DB::table('wp_bookings_raw')->where('wp_id', $post->ID)->first();

                // Побудова payload
                $extraMeta = [];
                if (!empty($passenger_extra_phones) && Schema::hasColumn('wp_bookings_raw','meta')) {
                    $extraMeta['extra_phones'] = $passenger_extra_phones;
                }

                $payload = [
                    'wp_id'                   => $post->ID,
                    'wp_order_id'             => $wp_order_id,
                    'wp_bus_id'               => $wp_bus_id,
                    'bus_id'                  => $busId,

                    // Пасажир
                    'passenger_name'          => $passenger_name ? trim($passenger_name) : null,
                    'passenger_email'         => $passenger_email ? trim($passenger_email) : null,
                    'passenger_phone'         => $passenger_phone,

                    // Хто оформляв
                    'booked_by_name'          => $booked_by_name,
                    'booked_by_email'         => $booked_by_email,
                    'booked_by_phone'         => $booked_by_phone,
                    'booked_by_user_id'       => $booked_by_user_id,
                    'booked_by_is_manager'    => $booked_by_is_manager,
                    'booked_by_is_third_party'=> $booked_by_is_third_party,

                    // Лінк на юзера (пасажира)
                    'user_id'                 => $userId,

                    // Маршрут/часи
                    'boarding_point'          => $boarding_pt,
                    'boarding_time'           => $boarding_tm,
                    'dropping_point'          => $dropping_pt,
                    'dropping_time'           => $dropping_tm,
                    'start_time'              => $start_tm,
                    'booking_date'            => $booking_dt,

                    // Квиток/ціни/стан
                    'ticket_type'             => $ticket_type,
                    'seat_number'             => $seat ? (string)$seat : null,
                    'fare'                    => $fare,
                    'total_price'             => $total_price,
                    'payment_method'          => $payment_method,
                    'order_status'            => $order_status,
                    'ticket_status'           => $ticket_status,

                    // Сирі дані
                    'attendee_info'           => is_array($attendee) ? json_encode($attendee, JSON_UNESCAPED_UNICODE) : null,
                    'extra_services'          => null,
                    'meta'                    => json_encode(array_merge([
                        'wbtm_user_id'         => $ms['wbtm_user_id']->meta_value ?? null,
                        'wbtm_item_id'         => $ms['wbtm_item_id']->meta_value ?? null,
                        'wbtm_bus_start_point' => $ms['wbtm_bus_start_point']->meta_value ?? null,
                        'wbtm_pickup_point'    => $ms['wbtm_pickup_point']->meta_value ?? null,
                        'wbtm_drop_off_point'  => $ms['wbtm_drop_off_point']->meta_value ?? null,
                        'wp_post_status'       => $post->post_status,
                        'wp_post_date'         => $post->post_date,
                        'wp_post_modified'     => $post->post_modified,
                    ], $extraMeta), JSON_UNESCAPED_UNICODE),
                ];

                if ($dry) {
                    $mode = $exists ? 'UPDATE' : 'CREATE';
                    $this->line(sprintf(
                        "[DRY-RUN][%s] wp_id=%d passenger=%s <%s> phone=%s | booked_by=%s <%s> phone=%s manager=%s third=%s | seat=%s price=%.2f status=%s",
                        $mode, $post->ID,
                        $payload['passenger_name'] ?? '-', $payload['passenger_email'] ?? '-', $payload['passenger_phone'] ?? '-',
                        $payload['booked_by_name'] ?? '-', $payload['booked_by_email'] ?? '-', $payload['booked_by_phone'] ?? '-',
                        $payload['booked_by_is_manager'] ? 'yes' : 'no',
                        $payload['booked_by_is_third_party'] ? 'yes' : 'no',
                        $payload['seat_number'] ?? '-', $payload['total_price'] ?? 0, $payload['order_status'] ?? '-'
                    ));
                    $exists ? $updated++ : $created++;
                    continue;
                }

                if ($exists) {
                    DB::table('wp_bookings_raw')->where('id', $exists->id)->update(array_merge(
                        $payload, ['updated_at' => now()]
                    ));
                    $updated++;
                } else {
                    DB::table('wp_bookings_raw')->insert(array_merge(
                        $payload, ['created_at' => now(), 'updated_at' => now()]
                    ));
                    $created++;
                }
            }
        });

        $this->info("Done. Created: {$created}, Updated: {$updated}, Skipped: {$skipped}".($dry ? ' [DRY-RUN]' : ''));
        return self::SUCCESS;
    }

    // === допоміжні методи ===

    private function extractPhones(string ...$raws): array
    {
        $all = [];
        foreach ($raws as $raw) {
            if (!$raw) continue;

            // Пряме виділення
            preg_match_all('/\+?\d{6,15}/u', $raw, $m);
            foreach ($m[0] ?? [] as $hit) {
                $n = $this->normalizePhone($hit);
                if ($n) $all[$n] = true;
            }

            // Розбиття у випадку двох номерів без роздільників типу "+380...+48..."
            $spaced = preg_replace('/\+(?=\d)/', ' +', $raw);
            if ($spaced !== $raw) {
                preg_match_all('/\+?\d{6,15}/u', $spaced, $m2);
                foreach ($m2[0] ?? [] as $hit) {
                    $n = $this->normalizePhone($hit);
                    if ($n) $all[$n] = true;
                }
            }
        }
        return array_keys($all);
    }

    private function normalizePhone(string $s): ?string
    {
        $s = trim($s);
        $hasPlus = str_starts_with($s, '+');
        $digits = preg_replace('/\D+/', '', $s);
        if (!$digits) return null;
        $len = strlen($digits);
        if ($len < 7 || $len > 15) return null;
        return $hasPlus ? ('+'.$digits) : $digits;
    }

    private function pickPrimaryPhone(array $phones): array
    {
        $intl = array_values(array_filter($phones, fn($p) => str_starts_with($p, '+')));
        $primary = $intl[0] ?? ($phones[0] ?? null);
        $extras = array_values(array_filter($phones, fn($p) => $p !== $primary));
        return [$primary, $extras];
    }

    private function normEmail(?string $e): ?string
    {
        $e = $e ? trim($e) : null;
        return $e ? strtolower($e) : null;
    }

    private function normName(?string $s): ?string
    {
        if (!$s) return null;
        $s = mb_strtolower(trim($s), 'UTF-8');
        // прибираємо лапки/апострофи/дефіси/зайві пробіли
        $s = preg_replace('/[\'"’`´\-]+/u', ' ', $s);
        $s = preg_replace('/\s+/u', ' ', $s);
        return $s ?: null;
    }
}
