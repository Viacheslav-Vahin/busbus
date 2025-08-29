<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // якщо було string('wp_password') NOT NULL — зробимо nullable
            $table->string('wp_password', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // повернення назад (якщо потрібно)
            $table->string('wp_password', 255)->nullable(false)->default('')->change();
        });
    }
};
