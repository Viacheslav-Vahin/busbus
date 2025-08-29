<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('wp_bookings_raw', function (Blueprint $table) {
            if (!Schema::hasColumn('wp_bookings_raw', 'booked_by_name')) {
                $table->string('booked_by_name')->nullable()->after('passenger_phone');
            }
            if (!Schema::hasColumn('wp_bookings_raw', 'booked_by_email')) {
                $table->string('booked_by_email')->nullable()->after('booked_by_name');
            }
            if (!Schema::hasColumn('wp_bookings_raw', 'booked_by_phone')) {
                $table->string('booked_by_phone')->nullable()->after('booked_by_email');
            }
            if (!Schema::hasColumn('wp_bookings_raw', 'booked_by_user_id')) {
                $table->unsignedBigInteger('booked_by_user_id')->nullable()->after('booked_by_phone');
                // за потреби потім додаси FK на users(id)
            }
            if (!Schema::hasColumn('wp_bookings_raw', 'booked_by_is_manager')) {
                $table->boolean('booked_by_is_manager')->default(false)->after('booked_by_user_id');
            }
            if (!Schema::hasColumn('wp_bookings_raw', 'booked_by_is_third_party')) {
                $table->boolean('booked_by_is_third_party')->default(false)->after('booked_by_is_manager');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wp_bookings_raw', function (Blueprint $table) {
            foreach ([
                         'booked_by_is_third_party',
                         'booked_by_is_manager',
                         'booked_by_user_id',
                         'booked_by_phone',
                         'booked_by_email',
                         'booked_by_name',
                     ] as $col) {
                if (Schema::hasColumn('wp_bookings_raw', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
