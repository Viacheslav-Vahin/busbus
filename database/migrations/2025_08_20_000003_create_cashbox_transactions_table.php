<?php
// database/migrations/2025_08_20_000003_create_cashbox_transactions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('cashbox_transactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('shift_id')->constrained('driver_shifts');
            $t->foreignId('booking_id')->nullable()->constrained('bookings');
            $t->foreignId('driver_id')->constrained('users');
            $t->string('type'); // collect_cash|refund_cash|deposit_terminal|adjustment
            $t->decimal('amount_uah', 12, 2);
            $t->decimal('amount', 12, 2);
            $t->string('currency_code', 3)->default('UAH');
            $t->decimal('fx_rate', 10, 6)->default(1);
            $t->json('meta')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('cashbox_transactions'); }
};
