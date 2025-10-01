<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('gallery_photos', function (Blueprint $table) {
            // короткий base64-прев’ю, який генеруємо після завантаження
            $table->text('placeholder')->nullable()->after('path');

            // опис/alt-текст із форми
            $table->text('alt')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('gallery_photos', function (Blueprint $table) {
            $table->dropColumn(['placeholder', 'alt']);
        });
    }
};
