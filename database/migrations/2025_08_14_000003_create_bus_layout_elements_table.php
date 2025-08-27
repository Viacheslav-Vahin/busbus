<?php
// database/migrations/2025_08_14_000003_create_bus_layout_elements_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('bus_layout_elements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('bus_id')->constrained()->cascadeOnDelete();
            $t->enum('type', ['wc','coffee','driver','stuardesa','stairs','exit'])->index();
            $t->unsignedSmallInteger('x')->default(0);
            $t->unsignedSmallInteger('y')->default(0);
            $t->unsignedTinyInteger('w')->default(1);   // розмір в клітинках
            $t->unsignedTinyInteger('h')->default(1);
            $t->string('label')->nullable();
            $t->json('meta')->nullable();
            $t->timestamps();
            $t->index(['bus_id','x','y']);
        });
    }
    public function down(): void { Schema::dropIfExists('bus_layout_elements'); }
};
