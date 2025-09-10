<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (! $this->indexExists('bookings', 'idx_bookings_report_filters')) {
            // Композитний індекс під фільтри сторінки
            DB::statement("
                CREATE INDEX idx_bookings_report_filters
                ON bookings (date, status, currency_code, agent_id, payment_method)
            ");
        }
    }

    public function down(): void
    {
        if ($this->indexExists('bookings', 'idx_bookings_report_filters')) {
            DB::statement("DROP INDEX idx_bookings_report_filters ON bookings");
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $schema = DB::getDatabaseName();
        $row = DB::selectOne("
            SELECT 1 AS x
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?
            LIMIT 1
        ", [$schema, $table, $indexName]);

        return (bool) ($row->x ?? false);
    }
};
