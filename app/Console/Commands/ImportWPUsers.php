<?php
// app/Console/Commands/ImportWPUsers.php
namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class ImportWPUsers extends Command
{
    protected $signature = 'wp:import-users {--dry-run} {--chunk=500}';
    protected $description = 'Імпорт користувачів з WordPress у локальну БД (роль passenger).';

    public function handle(): int
    {
        $dry = $this->option('dry-run');
        $chunk = (int) $this->option('chunk') ?: 500;

        // Роль passenger — створимо, якщо нема
        $roleName = 'passenger';
        $role = Role::where('name', $roleName)->first();
        if (!$role) {
            if ($dry) {
                $this->warn("[DRY-RUN] Role '{$roleName}' does not exist → will create.");
            } else {
                $role = Role::create(['name' => $roleName]);
                $this->info("Role '{$roleName}' created.");
            }
        }

        $wp = DB::connection('wordpress');
        $uTable = 'users';
        $mTable = 'usermeta';

        // з usermeta тягнемо first_name, last_name, billing_phone
        $metaKeys = ['first_name','last_name','billing_phone'];

        $total = DB::connection('wordpress')->table($uTable)->count();
        $this->info("Found {$total} WP users");

        $created = $updated = $skipped = 0;

        DB::connection('wordpress')->table($uTable)
            ->select([
                $uTable.'.ID as wp_id',
                $uTable.'.user_login',
                $uTable.'.user_email',
                $uTable.'.user_pass', // WP hash
                $uTable.'.user_registered',
            ])
            ->orderBy('ID')
            ->chunk($chunk, function ($rows) use ($dry, $metaKeys, $mTable, &$created, &$updated, &$skipped) {

                // зберемо meta для всього чанку одним запитом
                $wpIds = collect($rows)->pluck('wp_id')->all();
                $meta = DB::connection('wordpress')->table($mTable)
                    ->whereIn('user_id', $wpIds)
                    ->whereIn('meta_key', $metaKeys)
                    ->select('user_id','meta_key','meta_value')
                    ->get()
                    ->groupBy('user_id')
                    ->map(function ($items) {
                        $out = [];
                        foreach ($items as $it) $out[$it->meta_key] = $it->meta_value;
                        return $out;
                    });

                foreach ($rows as $r) {
                    $email = trim((string)$r->user_email);
                    $login = trim((string)$r->user_login);
                    $wpHash = (string)$r->user_pass;

                    $m = $meta->get($r->wp_id, []);
                    $first = (string)($m['first_name'] ?? '');
                    $last  = (string)($m['last_name'] ?? '');
                    $phone = (string)($m['billing_phone'] ?? '');

                    // sanitize phone трохи
                    $phone = preg_replace('/[^+0-9]/', '', $phone) ?: null;

                    // базове правило ідентифікації — по email, якщо нема — по login
                    $query = User::query();
                    if ($email !== '')      $query->orWhere('email', $email);
                    if ($login !== '')      $query->orWhere('name', $login); // фолбек
                    $user = $query->first();

                    if (!$user) {
                        // CREATE
                        if ($dry) {
                            $this->line(sprintf(
                                "[DRY-RUN][CREATE] %s (login: %s) phone=%s name=%s %s",
                                $email ?: '(no-email)', $login, $phone ?? '-', $first, $last
                            ));
                            $created++;
                            continue;
                        }

                        $user = new User();
                        $user->name    = $first ?: ($login ?: 'user_'.Str::random(6));
                        $user->surname = $last ?: null;
                        $user->email   = $email ?: null;
                        $user->phone   = $phone;
                        $user->password = bcrypt(Str::random(16)); // тимчасовий
                        $user->wp_password = $wpHash ?: null;      // головне — WP-хеш
                        $user->save();

                        // роль passenger
                        if (!$user->hasRole('passenger')) {
                            $user->assignRole('passenger');
                        }

                        $this->line("[CREATE] {$user->email} (id: {$user->id})");
                        $created++;
                    } else {
                        // UPDATE (дописуємо відсутні поля + оновлюємо wp_password)
                        $changes = [];
                        if (!$user->surname && $last)  { $user->surname = $last;   $changes[]='surname'; }
                        if (!$user->name && $first)    { $user->name    = $first;  $changes[]='name'; }
                        if (!$user->phone && $phone)   { $user->phone   = $phone;  $changes[]='phone'; }

                        // якщо ще не мігрувався пароль — підкинемо WP-хеш
                        if ($wpHash && !$user->wp_password && !$user->password) {
                            $user->wp_password = $wpHash;
                            $changes[]='wp_password';
                        }

                        if ($dry) {
                            $this->line(sprintf(
                                "[DRY-RUN][%s] %s (login: %s) changes=[%s]",
                                empty($changes)?'SKIP':'UPDATE',
                                $user->email ?: '(no-email)', $login, implode(',', $changes) ?: '-'
                            ));
                            empty($changes) ? $skipped++ : $updated++;
                            continue;
                        }

                        if (!empty($changes)) {
                            $user->save();
                            if (!$user->hasRole('passenger')) $user->assignRole('passenger');
                            $this->line("[UPDATE] {$user->email} (id: {$user->id}) changes=[".implode(',', $changes)."]");
                            $updated++;
                        } else {
                            // все вже було
                            if (!$user->hasRole('passenger')) {
                                if (!$dry) $user->assignRole('passenger');
                                $updated++;
                                $this->line("[UPDATE] {$user->email} add role passenger");
                            } else {
                                $skipped++;
                            }
                        }
                    }
                }
            });

        $this->info("Done. Created: {$created}, Updated: {$updated}, Skipped: {$skipped} ".($dry?'[DRY-RUN]':''));
        return self::SUCCESS;
    }
}
