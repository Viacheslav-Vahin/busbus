<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BackfillTripStopIdsSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Найточніше: мапимо по НАЗВАХ зупинок (уніфікуємо collation у виразі)
        DB::statement(<<<'SQL'
UPDATE trips t
LEFT JOIN stops s1
  ON s1.name COLLATE utf8mb4_unicode_ci = t.start_location COLLATE utf8mb4_unicode_ci
LEFT JOIN stops s2
  ON s2.name COLLATE utf8mb4_unicode_ci = t.end_location   COLLATE utf8mb4_unicode_ci
SET t.start_stop_id = COALESCE(t.start_stop_id, s1.id),
    t.end_stop_id   = COALESCE(t.end_stop_id,   s2.id)
WHERE (t.start_stop_id IS NULL OR t.end_stop_id IS NULL);
SQL);

        // 2) Фолбек: по ЧАСУ, але підтверджуємо, що назви теж збігаються
        DB::statement(<<<'SQL'
UPDATE trips t
JOIN bus_stops sb
  ON sb.bus_id = t.bus_id
 AND sb.type   = 'boarding'
 AND sb.time   = t.departure_time
JOIN stops s1
  ON s1.id = sb.stop_id
JOIN bus_stops se
  ON se.bus_id = t.bus_id
 AND se.type   = 'dropping'
 AND se.time   = t.arrival_time
JOIN stops s2
  ON s2.id = se.stop_id
SET t.start_stop_id = COALESCE(t.start_stop_id, sb.stop_id),
    t.end_stop_id   = COALESCE(t.end_stop_id,   se.stop_id)
WHERE (t.start_stop_id IS NULL OR t.end_stop_id IS NULL)
  AND s1.name COLLATE utf8mb4_unicode_ci = t.start_location COLLATE utf8mb4_unicode_ci
  AND s2.name COLLATE utf8mb4_unicode_ci = t.end_location   COLLATE utf8mb4_unicode_ci;
SQL);
    }
}
