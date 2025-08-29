<?php
// database/migrations/2025_08_28_000001_add_wp_id_to_buses_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('buses', function (Blueprint $table) {
            // якщо таблиця інша (наприклад vehicles), заміни 'buses' на актуальну
            if (!Schema::hasColumn('buses', 'wp_id')) {
                $table->unsignedBigInteger('wp_id')->nullable()->after('id');
                $table->index('wp_id', 'buses_wp_id_idx');
                // якщо впевнений(а), що один WP bus = один наш bus, можна замість index зробити unique:
                // $table->unique('wp_id', 'buses_wp_id_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('buses', function (Blueprint $table) {
            if (Schema::hasColumn('buses', 'wp_id')) {
                // прибрати індекси обережно — ім'я має збігатися з тим, що створили в up()
                $table->dropIndex('buses_wp_id_idx');
                // $table->dropUnique('buses_wp_id_unique');
                $table->dropColumn('wp_id');
            }
        });
    }
};
