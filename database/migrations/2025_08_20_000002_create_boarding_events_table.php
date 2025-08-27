<?php
// database/migrations/2025_08_20_000002_create_boarding_events_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('boarding_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('shift_id')->constrained('driver_shifts');
            $t->foreignId('booking_id')->constrained('bookings');
            $t->uuid('ticket_uuid');
            $t->timestamp('boarded_at');
            $t->foreignId('driver_id')->constrained('users');
            $t->string('status'); // boarded|denied|refunded
            $t->string('payment_method')->nullable(); // cash|card|paid_before
            $t->decimal('amount_uah', 12, 2)->default(0);
            $t->decimal('amount', 12, 2)->default(0);
            $t->string('currency_code', 3)->default('UAH');
            $t->decimal('fx_rate', 10, 6)->default(1);
            $t->decimal('lat', 10, 7)->nullable();
            $t->decimal('lng', 10, 7)->nullable();
            $t->json('payload')->nullable(); // сирі дані зі сканера/браузера
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('boarding_events'); }
};
