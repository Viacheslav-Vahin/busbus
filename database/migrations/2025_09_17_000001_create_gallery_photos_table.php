<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
{
    Schema::create('gallery_photos', function (Blueprint $table) {
        $table->id();
        $table->string('title')->nullable();
        $table->string('path');             // шлях до файлу (disk: public)
        $table->json('tags')->nullable();   // ["bus","poland"]
        $table->unsignedInteger('w')->nullable();
        $table->unsignedInteger('h')->nullable();
        $table->boolean('is_published')->default(true);
        $table->unsignedInteger('position')->default(0); // для ручного сорту
        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gallery_photos');
    }
};
