<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BackfillAgentIdFromWpViewSeeder extends Seeder
{
    public function run(): void
    {
        // ---- 0) Менеджерські емейли -> масив (lowercase)
        $emails = collect(explode(',', env('WP_MANAGER_EMAILS','')))
            ->map(fn($e)=>strtolower(trim($e)))
            ->filter()
            ->unique()
            ->values();

        if ($emails->isEmpty()) {
            $this->command?->warn('WP_MANAGER_EMAILS is empty — nothing to do.');
            return;
        }

        // ---- 1) Перевіримо/зіберемо user_id для цих емейлів у нашій БД
        $createMissing = filter_var(env('CREATE_MISSING_AGENT_USERS', false), FILTER_VALIDATE_BOOLEAN);
        $defaultRole   = env('DEFAULT_AGENT_ROLE', 'manager');

        $emailToUserId = [];
        foreach ($emails as $email) {
            $user = DB::connection('mysql')->table('users')->whereRaw('LOWER(email)=?', [$email])->first();
            if (!$user && $createMissing) {
                $name = Str::of($email)->before('@')->replace(['.','_','-'],' ')->title()->value();
                $id = DB::connection('mysql')->table('users')->insertGetId([
                    'name' => $name ?: 'Agent '.$email,
                    'email' => $email,
                    'password' => Hash::make(Str::random(16)),
                    'role' => DB::getSchemaBuilder()->hasColumn('users','role') ? $defaultRole : null,
                    'created_at' => now(),'updated_at' => now(),
                ]);
                // Якщо стоїть Spatie Permission — призначимо роль
                if (class_exists(\Spatie\Permission\Models\Role::class)) {
                    try {
                        $u = \App\Models\User::find($id);
                        if ($u && method_exists($u,'assignRole')) $u->assignRole($defaultRole);
                    } catch (\Throwable $e) {}
                }
                $user = DB::connection('mysql')->table('users')->where('id',$id)->first();
                $this->command?->info("Created user {$email} (id={$user->id})");
            }
            if ($user) $emailToUserId[$email] = $user->id;
        }

        // Залишаємо тільки емейли, які мають user_id
        $validEmails = collect(array_keys($emailToUserId));
        if ($validEmails->isEmpty()) {
            $this->command?->warn('None of manager emails exists in users table.');
            return;
        }

        // ---- 2) Визначимо ім’я view у WP: wp_bookings_clean або з префіксом
        $prefix = env('WP_DB_PREFIX', '');
        $candidates = array_filter([
            'wp_bookings_clean',
            $prefix ? "{$prefix}bookings_clean" : null,
            $prefix ? "{$prefix}wp_bookings_clean" : null,
        ]);
        $wpView = null;
        foreach ($candidates as $name) {
            try {
                // пробуємо 1 запис — якщо ок, беремо це ім'я
                $exists = DB::connection('wordpress')->table($name)->limit(1)->get();
                $wpView = $name;
                break;
            } catch (\Throwable $e) {
                // пробуємо наступну назву
            }
        }
        if (!$wpView) {
            $this->command?->error("Can't find wp_bookings_clean (tried: ".implode(', ', $candidates).")");
            return;
        }
        $this->command?->info("Using WP view: {$wpView}");

        // ---- 3) Створимо TEMP таблицю в НАШІЙ БД і наповнимо даними з WP
        DB::connection('mysql')->statement('DROP TEMPORARY TABLE IF EXISTS tmp_wp_manager_bookings');
        DB::connection('mysql')->statement("
            CREATE TEMPORARY TABLE tmp_wp_manager_bookings (
                bus_id BIGINT UNSIGNED NOT NULL,
                start_date DATE NOT NULL,
                seat_number INT NOT NULL,
                email VARCHAR(255) NOT NULL,
                total_price DECIMAL(12,2) NULL,
                KEY idx_bsd (bus_id, start_date, seat_number),
                KEY idx_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Читаємо WP view батчами і вливаємо тільки потрібні емейли
        DB::connection('wordpress')->table($wpView)
            ->select(['bus_id','start_date','seat_number','booked_by_email','total_price'])
            ->whereIn(DB::raw('LOWER(booked_by_email)'), $validEmails->all())
            ->orderBy('id')
            ->chunk(1000, function ($rows) {
                $bulk = [];
                foreach ($rows as $r) {
                    $email = strtolower((string)$r->booked_by_email);
                    if (!$email) continue;
                    $bulk[] = [
                        'bus_id'      => (int)$r->bus_id,
                        'start_date'  => $r->start_date,
                        'seat_number' => (int)$r->seat_number,
                        'email'       => $email,
                        'total_price' => $r->total_price !== null ? (float)$r->total_price : null,
                    ];
                }
                if ($bulk) {
                    // вставляємо у ТУ Ж САМУ (mysql) конекшен, де створено TEMP-таблицю
                    DB::connection('mysql')->table('tmp_wp_manager_bookings')->insert($bulk);
                }
            });

        // ---- 4) Оновлюємо bookings.agent_id через UPDATE ... JOIN
        $priceEps   = is_null(env('WP_PRICE_EPSILON')) ? null : (float)env('WP_PRICE_EPSILON');
        $dryRun     = filter_var(env('WP_DRY_RUN', false), FILTER_VALIDATE_BOOLEAN);

        // Базовий JOIN
        $joinSql = "
            FROM bookings b
            JOIN tmp_wp_manager_bookings t
              ON t.bus_id = b.bus_id
             AND t.start_date = b.date
             AND t.seat_number = b.seat_number
            JOIN users u
              ON LOWER(u.email) = t.email
            WHERE b.agent_id IS NULL
        ";

        // За потреби — додаткове звіряння по сумі
        if (!is_null($priceEps)) {
            $joinSql .= " AND t.total_price IS NOT NULL AND ABS(b.price - t.total_price) <= ".sprintf('%.4f', $priceEps);
        }

        if ($dryRun) {
            $cnt = DB::connection('mysql')->selectOne("SELECT COUNT(*) AS c {$joinSql}")->c ?? 0;
            $this->command?->info("DRY RUN: would update {$cnt} rows.");
        } else {
            $updated = DB::connection('mysql')->update("UPDATE bookings b
                JOIN tmp_wp_manager_bookings t
                  ON t.bus_id = b.bus_id
                 AND t.start_date = b.date
                 AND t.seat_number = b.seat_number
                JOIN users u
                  ON LOWER(u.email) = t.email
                SET b.agent_id = u.id
                WHERE b.agent_id IS NULL"
                .(!is_null($priceEps) ? " AND t.total_price IS NOT NULL AND ABS(b.price - t.total_price) <= ".sprintf('%.4f', $priceEps) : "")
            );
            $this->command?->info("Updated bookings.agent_id: {$updated}");
        }

        // Підсумок
        $totalNow = DB::connection('mysql')->table('bookings')->whereNotNull('agent_id')->count();
        $this->command?->info("Total bookings with agent_id NOT NULL: {$totalNow}");
    }
}
