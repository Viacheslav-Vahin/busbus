<?php
// database/migrations/2025_08_28_000002_add_booked_by_to_wp_bookings_raw.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('wp_bookings_raw', function (Blueprint $t) {
            $t->string('booked_by_name')->nullable()->after('passenger_phone');
            $t->string('booked_by_email')->nullable()->after('booked_by_name');
            $t->string('booked_by_phone')->nullable()->after('booked_by_email');
            $t->boolean('booked_by_manager')->default(false)->after('booked_by_phone');
        });
    }
    public function down(): void {
        Schema::table('wp_bookings_raw', function (Blueprint $t) {
            $t->dropColumn(['booked_by_name','booked_by_email','booked_by_phone','booked_by_manager']);
        });
    }
};
