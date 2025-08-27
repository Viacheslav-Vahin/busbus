<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // додаємо ПІБ і телефон
            if (!Schema::hasColumn('users', 'surname')) {
                $table->string('surname')->nullable()->after('name');
            }
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 64)->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'surname')) {
                $table->dropColumn('surname');
            }
            if (Schema::hasColumn('users', 'phone')) {
                $table->dropColumn('phone');
            }
        });
    }
};
