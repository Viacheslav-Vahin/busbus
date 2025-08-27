<?php
// database/migrations/2025_08_20_000001_create_driver_shifts_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('driver_shifts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('driver_id')->constrained('users');
            $t->foreignId('bus_id')->constrained('buses');
            $t->foreignId('route_id')->constrained('routes');
            $t->date('service_date');               // дата рейсу
            $t->timestamp('started_at')->nullable();
            $t->timestamp('ended_at')->nullable();
            $t->decimal('opening_cash', 12, 2)->default(0);
            $t->decimal('cash_collected', 12, 2)->default(0);
            $t->decimal('card_collected', 12, 2)->default(0);
            $t->decimal('terminal_deposit', 12, 2)->default(0); // сума здачі
            $t->unsignedInteger('passengers_boarded')->default(0);
            $t->unsignedInteger('tickets_count')->default(0);
            $t->string('status')->default('open');  // open|closed|submitted
            $t->json('meta')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('driver_shifts'); }
};
