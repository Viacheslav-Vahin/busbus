<?php
// database/migrations/2025_08_14_000002_create_bus_seats_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('bus_seats', function (Blueprint $t) {
            $t->id();
            $t->foreignId('bus_id')->constrained()->cascadeOnDelete();
            $t->string('number');                   // відображуваний номер місця
            $t->foreignId('seat_type_id')->nullable()->constrained('seat_types')->nullOnDelete();
            $t->unsignedSmallInteger('x')->nullable(); // позиція на сітці
            $t->unsignedSmallInteger('y')->nullable();
            $t->decimal('price_modifier_abs', 10, 2)->nullable();
            $t->decimal('price_modifier_pct', 5, 2)->nullable();
            $t->boolean('is_active')->default(true);
            $t->json('meta')->nullable();
            $t->timestamps();
            $t->unique(['bus_id','number']);
            $t->index(['bus_id','x','y']);
        });
    }
    public function down(): void { Schema::dropIfExists('bus_seats'); }
};
