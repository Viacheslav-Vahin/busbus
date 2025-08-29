<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // додаємо лише те, чого точно немає
            if (!Schema::hasColumn('users', 'wp_password')) {
                $table->string('wp_password', 255)
                    ->nullable()
                    ->after('password')
                    ->comment('Legacy WordPress password hash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'wp_password')) {
                $table->dropColumn('wp_password');
            }
        });
    }
};
