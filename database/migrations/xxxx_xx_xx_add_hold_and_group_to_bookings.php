<?php
// database/migrations/xxxx_xx_xx_add_hold_and_group_to_bookings.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('bookings', function (Blueprint $table) {
            $table->uuid('order_id')->nullable()->index();      // група бронювань (1..N місць)
            $table->uuid('hold_token')->nullable()->unique();   // токен утримання
            $table->timestamp('held_until')->nullable()->index(); // дедлайн холда
            $table->enum('status', ['hold','pending','paid','expired','cancelled'])
                ->default('hold')->change(); // якщо enum не зручно — тримай як varchar
            $table->boolean('is_solo_companion')->default(false); // «сусід без пасажира»
            $table->decimal('discount_pct',5,2)->nullable(); // наприклад 20.00 для другого місця
        });
    }
    public function down(): void {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['order_id','hold_token','held_until','is_solo_companion','discount_pct']);
            // статус вертай при необхідності вручну
        });
    }
};
