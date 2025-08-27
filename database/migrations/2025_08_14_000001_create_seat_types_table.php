<?php
// database/migrations/2025_08_14_000001_create_seat_types_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('seat_types', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();          // classic, recliner, panoramic
            $t->string('name');                    // Класичне / Реклайнер / Панорамне
            $t->enum('modifier_type', ['percent','absolute'])->default('percent');
            $t->decimal('modifier_value', 8, 2)->default(0); // +10% або +100.00
            $t->string('icon')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('seat_types'); }
};

