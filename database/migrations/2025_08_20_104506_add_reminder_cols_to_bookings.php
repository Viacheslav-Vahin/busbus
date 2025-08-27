<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('bookings', function (Blueprint $t) {
            $t->timestamp('reminder_24h_sent_at')->nullable()->after('paid_at');
            $t->timestamp('reminder_2h_sent_at')->nullable()->after('reminder_24h_sent_at');
        });
    }
    public function down(): void {
        Schema::table('bookings', function (Blueprint $t) {
            $t->dropColumn(['reminder_24h_sent_at', 'reminder_2h_sent_at']);
        });
    }
};
