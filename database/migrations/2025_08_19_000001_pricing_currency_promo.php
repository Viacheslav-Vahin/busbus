<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // promo codes
        Schema::create('promo_codes', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();           // UPPER
            $t->enum('type', ['percent', 'fix']);   // % або фіксована сума (у базовій валюті UAH)
            $t->decimal('value', 10, 2);
            $t->unsignedInteger('max_uses')->nullable();
            $t->unsignedInteger('per_user_limit')->nullable();
            $t->decimal('min_amount', 10, 2)->nullable(); // у UAH
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('used')->default(0);
            $t->json('meta')->nullable();
            $t->timestamps();
        });

        // price rules
        Schema::create('price_rules', function (Blueprint $t) {
            $t->id();
            $t->enum('scope_type', ['route','trip','bus','seat_class','seat_number']);
            $t->unsignedBigInteger('scope_id')->nullable();
            $t->unsignedInteger('seat_number')->nullable(); // коли scope_type=seat_number
            $t->decimal('amount_uah', 10, 2);               // сума в UAH
            $t->integer('priority')->default(100);
            $t->json('days_of_week')->nullable();           // ["Mon","Tue",...]
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // bookings доп. поля
        Schema::table('bookings', function (Blueprint $t) {
            if (!Schema::hasColumn('bookings','fx_rate')) {
                $t->decimal('fx_rate', 12, 6)->nullable()->after('currency_code'); // курс UAH->currency_code
            }
            if (!Schema::hasColumn('bookings','price_uah')) {
                $t->decimal('price_uah', 10, 2)->nullable()->after('price');       // фіксація в UAH
            }
            if (!Schema::hasColumn('bookings','discount_amount')) {
                $t->decimal('discount_amount', 10, 2)->default(0)->after('price'); // у UAH
            }
            if (!Schema::hasColumn('bookings','promo_code')) {
                $t->string('promo_code')->nullable()->after('discount_amount');
            }
            if (!Schema::hasColumn('bookings','pricing')) {
                $t->json('pricing')->nullable()->after('additional_services');     // розкладка
            }
        });
    }

    public function down(): void {
        Schema::table('bookings', function (Blueprint $t) {
            $t->dropColumn(['fx_rate','price_uah','discount_amount','promo_code','pricing']);
        });
        Schema::dropIfExists('price_rules');
        Schema::dropIfExists('promo_codes');
        Schema::dropIfExists('currencies');
    }
};
