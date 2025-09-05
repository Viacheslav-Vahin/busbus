<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            if (!Schema::hasColumn('trips', 'route_id')) {
                $table->foreignId('route_id')->nullable()->constrained('routes')->nullOnDelete()->after('bus_id');
            }
            if (!Schema::hasColumn('trips', 'start_stop_id')) {
                $table->foreignId('start_stop_id')->nullable()->constrained('stops')->restrictOnDelete()->after('route_id');
            }
            if (!Schema::hasColumn('trips', 'end_stop_id')) {
                $table->foreignId('end_stop_id')->nullable()->constrained('stops')->restrictOnDelete()->after('start_stop_id');
            }
        });

        // Унікальний ключ (захист від дублів)
        try {
            DB::statement("ALTER TABLE trips ADD CONSTRAINT uq_trip_bus_from_to_time UNIQUE (bus_id, start_stop_id, end_stop_id, departure_time)");
        } catch (\Throwable $e) {}

        // Індекс на напрямок
        try {
            DB::statement("CREATE INDEX idx_trips_from_to ON trips (start_stop_id, end_stop_id)");
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        try { DB::statement("DROP INDEX idx_trips_from_to ON trips"); } catch (\Throwable $e) {}
        try { DB::statement("ALTER TABLE trips DROP INDEX uq_trip_bus_from_to_time"); } catch (\Throwable $e) {}

        Schema::table('trips', function (Blueprint $table) {
            if (Schema::hasColumn('trips', 'end_stop_id')) {
                $table->dropConstrainedForeignId('end_stop_id');
            }
            if (Schema::hasColumn('trips', 'start_stop_id')) {
                $table->dropConstrainedForeignId('start_stop_id');
            }
            if (Schema::hasColumn('trips', 'route_id')) {
                $table->dropConstrainedForeignId('route_id');
            }
        });
    }
};
