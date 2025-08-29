<?php
// database/migrations/2025_08_28_130000_add_booked_by_fields_to_wp_bookings_raw.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('wp_bookings_raw', function (Blueprint $table) {
            if (!Schema::hasColumn('wp_bookings_raw', 'booked_by_name'))   $table->string('booked_by_name', 191)->nullable()->after('passenger_phone');
            if (!Schema::hasColumn('wp_bookings_raw', 'booked_by_email'))  $table->string('booked_by_email', 191)->nullable()->after('booked_by_name');
            if (!Schema::hasColumn('wp_bookings_raw', 'booked_by_phone'))  $table->string('booked_by_phone', 32)->nullable()->after('booked_by_email');
            if (!Schema::hasColumn('wp_bookings_raw', 'booked_by_user_id'))$table->unsignedBigInteger('booked_by_user_id')->nullable()->after('user_id');
            if (!Schema::hasColumn('wp_bookings_raw', 'booked_by_is_manager')) $table->boolean('booked_by_is_manager')->default(false)->after('booked_by_user_id');

            // Щоб мати змогу зберігати дод.телефони, якщо ще нема meta
            if (!Schema::hasColumn('wp_bookings_raw', 'meta')) {
                $table->json('meta')->nullable()->after('extra_services');
            }

            // опційно
            $table->index(['booked_by_email'], 'wpb_raw_booked_by_email_idx');
            $table->index(['booked_by_user_id'], 'wpb_raw_booked_by_uid_idx');
        });
    }

    public function down(): void
    {
        Schema::table('wp_bookings_raw', function (Blueprint $table) {
            if (Schema::hasColumn('wp_bookings_raw', 'booked_by_name'))       $table->dropColumn('booked_by_name');
            if (Schema::hasColumn('wp_bookings_raw', 'booked_by_email'))      $table->dropColumn('booked_by_email');
            if (Schema::hasColumn('wp_bookings_raw', 'booked_by_phone'))      $table->dropColumn('booked_by_phone');
            if (Schema::hasColumn('wp_bookings_raw', 'booked_by_user_id'))    $table->dropColumn('booked_by_user_id');
            if (Schema::hasColumn('wp_bookings_raw', 'booked_by_is_manager')) $table->dropColumn('booked_by_is_manager');

            if (Schema::hasColumn('wp_bookings_raw', 'wpb_raw_booked_by_email_idx')) $table->dropIndex('wpb_raw_booked_by_email_idx');
            if (Schema::hasColumn('wp_bookings_raw', 'wpb_raw_booked_by_uid_idx'))   $table->dropIndex('wpb_raw_booked_by_uid_idx');
            // meta не чіпаємо в down(), бо могла вже використовуватись
        });
    }
};
