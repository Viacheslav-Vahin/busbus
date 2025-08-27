<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $t) {
            $t->string('payment_method')->nullable();   // 'card:WayForPay' / 'cash' / etc
            $t->string('invoice_number')->nullable();   // наш внутрішній номер
            $t->decimal('tax_rate', 5, 2)->nullable();  // 0 або 20, якщо треба
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            //
        });
    }
};
