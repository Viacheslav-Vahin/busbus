<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_payment_meta_and_paid_at_to_bookings_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings','payment_meta')) {
                $table->json('payment_meta')->nullable()->after('status');
            }
            if (!Schema::hasColumn('bookings','paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('status');
            }
        });
    }
    public function down(): void {
        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings','payment_meta')) $table->dropColumn('payment_meta');
            if (Schema::hasColumn('bookings','paid_at')) $table->dropColumn('paid_at');
        });
    }
};
