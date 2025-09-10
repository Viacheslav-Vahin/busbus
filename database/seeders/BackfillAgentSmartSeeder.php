<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BackfillAgentSmartSeeder extends Seeder
{
    public function run(): void
    {
        // ===== Параметри з .env
        $wpDb      = DB::connection('wordpress')->getDatabaseName();
        $prefix    = env('WP_DB_PREFIX', '');
        $src       = env('WP_BOOKINGS_VIEW_NAME', 'wp_bookings_clean'); // явно вказати якщо треба
        $joinMode  = env('WP_JOIN_MODE', 'bus_date_seat'); // bus_date_seat | bus_date_seat_price | wpbus_date_seat | date_seat_price
        $dryRun    = filter_var(env('WP_DRY_RUN', false), FILTER_VALIDATE_BOOLEAN);
        $eps       = env('WP_PRICE_EPSILON'); $eps = is_null($eps) ? null : (float) $eps;
        $useList   = filter_var(env('WP_LIMIT_TO_EMAIL_LIST', false), FILTER_VALIDATE_BOOLEAN); // true => беремо тільки з WP_MANAGER_EMAILS

        // Менеджерські e-mail’и
        $emailsCsv = env('WP_MANAGER_EMAILS', '');
        $managerEmails = collect(explode(',', $emailsCsv))
            ->map(fn($e)=>strtolower(trim($e)))->filter()->unique()->values();

        // 0) Користувачі в нашій БД (створити відсутніх за бажанням)
        $createMissing = filter_var(env('CREATE_MISSING_AGENT_USERS', false), FILTER_VALIDATE_BOOLEAN);
        $defaultRole   = env('DEFAULT_AGENT_ROLE', 'manager');
        $emailToUserId = [];

        foreach ($managerEmails as $email) {
            $user = DB::table('users')->whereRaw('LOWER(email)=?', [$email])->first();
            if (!$user && $createMissing) {
                $name = Str::of($email)->before('@')->replace(['.','_','-'],' ')->title()->value();
                $id = DB::table('users')->insertGetId([
                    'name'=>$name ?: 'Agent '.$email,
                    'email'=>$email,
                    'password'=>Hash::make(Str::random(16)),
                    'role'=> DB::getSchemaBuilder()->hasColumn('users','role') ? $defaultRole : null,
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
                if (class_exists(\Spatie\Permission\Models\Role::class)) {
                    try { $u = \App\Models\User::find($id); if ($u && method_exists($u,'assignRole')) $u->assignRole($defaultRole); } catch (\Throwable $e) {}
                }
                $user = DB::table('users')->where('id',$id)->first();
                $this->command?->info("Created user {$email} (id={$user->id})");
            }
            if ($user) $emailToUserId[$email] = $user->id;
        }

        if ($useList && empty($emailToUserId)) {
            $this->command?->warn('WP_LIMIT_TO_EMAIL_LIST=true, але жодного email з WP_MANAGER_EMAILS немає в users.');
            return;
        }

        // 1) З’ясуємо, чи бачимо джерело у WP
        $srcFqn = "`{$wpDb}`.`{$src}`";
        try {
            DB::connection('wordpress')->select("SELECT 1 FROM {$srcFqn} LIMIT 1");
        } catch (\Throwable $e) {
            $this->command?->error("WP source not found: {$srcFqn}. Задай WP_BOOKINGS_VIEW_NAME=...");
            return;
        }
        $this->command?->info("Using WP source: {$srcFqn}");

        // 2) Створюємо TEMP-таблицю з необхідними полями
        DB::statement('DROP TEMPORARY TABLE IF EXISTS tmp_wp_manager_bookings');
        DB::statement("
            CREATE TEMPORARY TABLE tmp_wp_manager_bookings (
                wp_id BIGINT UNSIGNED NULL,
                wp_bus_id BIGINT UNSIGNED NULL,
                bus_id BIGINT UNSIGNED NULL,
                start_date DATE NOT NULL,
                seat_number INT NULL,
                email VARCHAR(255) NOT NULL,
                total_price DECIMAL(12,2) NULL,
                INDEX idx1 (bus_id, start_date, seat_number),
                INDEX idx2 (wp_bus_id, start_date, seat_number),
                INDEX idx3 (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 3) Заливаємо дані з WP:
        //    - беремо ВСІ записи booked_by_is_manager=1
        //    - якщо WP_LIMIT_TO_EMAIL_LIST=true — ще й фільтруємо по нашому списку email
        //    - якщо booked_by_email порожній — беремо email із {$prefix}users за booked_by_user_id
        $wpDbBookings = DB::connection('wordpress')->getDatabaseName(); // u303756778_bus_booking_db
        $srcFqn = "`{$wpDbBookings}`.`".env('WP_BOOKINGS_VIEW_NAME','wp_bookings_clean')."`";

        $wpUsersDb   = env('WP_USERS_DB_DATABASE', $wpDbBookings); // <-- ВАЖЛИВО: тут твій u303756778_maxbus_prod
        $wpUsersTbl  = env('WP_USERS_TABLE', (env('WP_DB_PREFIX','') ?: '').'users');
        $wpUsersCol  = env('WP_USERS_EMAIL_COLUMN', 'user_email');
        $wpUsersFqn  = "`{$wpUsersDb}`.`{$wpUsersTbl}`";

// опційний фільтр списком email
        $useList = filter_var(env('WP_LIMIT_TO_EMAIL_LIST', false), FILTER_VALIDATE_BOOLEAN);
        $emails  = collect(explode(',', env('WP_MANAGER_EMAILS','')))->map(fn($e)=>strtolower(trim($e)))->filter()->values();
        $params  = [];
        $where   = "WHERE w.booked_by_is_manager = 1";
        if ($useList && $emails->isNotEmpty()) {
            $ph = implode(',', array_fill(0, $emails->count(), '?'));
            $where .= " AND (LOWER(w.booked_by_email) IN ({$ph}) OR LOWER(u.{$wpUsersCol}) IN ({$ph}))";
            $params = array_merge($emails->all(), $emails->all());
        }

// ТЕПЕР правильний JOIN на WP users і вибір email з fallback'ом
        $insertSql = "
    INSERT INTO tmp_wp_manager_bookings (wp_id, wp_bus_id, bus_id, start_date, seat_number, email, total_price)
    SELECT
        w.wp_id,
        w.wp_bus_id,
        w.bus_id,
        w.start_date,
        NULLIF(w.seat_number, 0) as seat_number,
        COALESCE(NULLIF(LOWER(w.booked_by_email), ''), LOWER(u.{$wpUsersCol})) as email,
        w.total_price
    FROM {$srcFqn} w
    LEFT JOIN {$wpUsersFqn} u ON u.ID = w.booked_by_user_id
    {$where}
";
        DB::statement($insertSql, $params);

        // Діагностика вставленого
        $cntTmp = DB::table('tmp_wp_manager_bookings')->count();
        $this->command?->info("TMP rows inserted: {$cntTmp}");
        $sample = DB::table('tmp_wp_manager_bookings')->limit(5)->get();
        foreach ($sample as $row) {
            $this->command?->line("  sample: wp_id={$row->wp_id}, bus_id={$row->bus_id}, wp_bus_id={$row->wp_bus_id}, date={$row->start_date}, seat={$row->seat_number}, email={$row->email}, price={$row->total_price}");
        }

        // 4) Побудова JOIN умов за режимом
        $joinCond = match ($joinMode) {
            'bus_date_seat'       => 't.bus_id = b.bus_id AND t.start_date = b.date AND t.seat_number = b.seat_number',
            'bus_date_seat_price' => 't.bus_id = b.bus_id AND t.start_date = b.date AND t.seat_number = b.seat_number',
            'wpbus_date_seat'     => 't.wp_bus_id = b.bus_id AND t.start_date = b.date AND t.seat_number = b.seat_number',
            'date_seat_price'     => 't.start_date = b.date AND t.seat_number = b.seat_number',
            default               => 't.bus_id = b.bus_id AND t.start_date = b.date AND t.seat_number = b.seat_number',
        };

        // COUNT для dry-run/діагностики
        $priceClause = (!is_null($eps) && in_array($joinMode, ['bus_date_seat_price','date_seat_price'], true))
            ? " AND t.total_price IS NOT NULL AND ABS(b.price - t.total_price) <= ".sprintf('%.4f',$eps)
            : '';

        $countSql = "
            SELECT COUNT(*) AS c
            FROM bookings b
            JOIN tmp_wp_manager_bookings t ON {$joinCond}
            JOIN users u ON LOWER(u.email) = t.email
            WHERE b.agent_id IS NULL
            {$priceClause}
        ";
        $would = DB::selectOne($countSql)->c ?? 0;
        $this->command?->info("Join mode: {$joinMode}".(!is_null($eps) ? " (eps={$eps})" : '')." -> candidates: {$would}");

        if ($dryRun) {
            $this->command?->info('DRY RUN: no updates performed.');
            $this->printHints($would, $joinMode);
            return;
        }

        // UPDATE
        $updateSql = "
            UPDATE bookings b
            JOIN tmp_wp_manager_bookings t ON {$joinCond}
            JOIN users u ON LOWER(u.email) = t.email
            SET b.agent_id = u.id
            WHERE b.agent_id IS NULL
            {$priceClause}
        ";
        $updated = DB::update($updateSql);
        $this->command?->info("Updated bookings.agent_id: {$updated}");

        $totalNow = DB::table('bookings')->whereNotNull('agent_id')->count();
        $this->command?->info("Total bookings with agent_id NOT NULL: {$totalNow}");
    }

    private function printHints(int $candidates, string $joinMode): void
    {
        if ($candidates > 0) return;

        $this->command?->warn('0 кандидатів. Спробуй одне з наступного:');
        $this->command?->line(' - Перемкни режим з’єднання: WP_JOIN_MODE=wpbus_date_seat або WP_JOIN_MODE=date_seat_price');
        $this->command?->line(' - Додай перевірку суми: WP_PRICE_EPSILON=0.01 і режим *_price');
        $this->command?->line(' - Якщо у WP booked_by_email відсутній/інший — ми вже підтягуємо email із WP users, тож це ок.');
        $this->command?->line(' - Переконайся, що в users існують відповідні менеджерські e-mail (або створи їх).');
        $this->command?->line(' - Подивись 5 sample вище і порівняй з bookings на ці дати/місця.');
    }
}
