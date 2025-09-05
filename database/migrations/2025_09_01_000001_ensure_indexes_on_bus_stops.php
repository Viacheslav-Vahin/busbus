<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        try {
            DB::statement("ALTER TABLE bus_stops ADD CONSTRAINT uq_bus_stop UNIQUE (bus_id, stop_id, type)");
        } catch (\Throwable $e) {}

        try {
            DB::statement("CREATE INDEX idx_bus_stops_bus_type_stop ON bus_stops (bus_id, type, stop_id)");
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        try { DB::statement("DROP INDEX idx_bus_stops_bus_type_stop ON bus_stops"); } catch (\Throwable $e) {}
        try { DB::statement("ALTER TABLE bus_stops DROP INDEX uq_bus_stop"); } catch (\Throwable $e) {}
    }
};
