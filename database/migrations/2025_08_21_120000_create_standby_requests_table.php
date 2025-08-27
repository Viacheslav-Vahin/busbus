<?php
// database/migrations/2025_08_21_120000_create_standby_requests_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('standby_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $t->foreignId('route_id')->nullable()->constrained('routes')->nullOnDelete();
            $t->date('date');                       // дата рейсу
            $t->unsignedInteger('seats_requested'); // скільки потрібно місць
            $t->boolean('allow_partial')->default(false); // дозволити часткове підбирання (1 з 2)
            // контакти платника
            $t->string('name')->nullable();
            $t->string('surname')->nullable();
            $t->string('email')->nullable();
            $t->string('phone')->nullable();

            // сума/валюта
            $t->decimal('amount', 10, 2);       // у валюті замовлення
            $t->string('currency_code', 8)->default('UAH');
            $t->decimal('amount_uah', 12, 2);   // еквівалент у UAH
            $t->decimal('fx_rate', 12, 6)->nullable();

            // WayForPay
            $t->string('order_reference')->unique(); // наш orderReference
            $t->string('w4p_invoice_id')->nullable();
            $t->string('w4p_auth_code')->nullable();

            // життєвий цикл
            $t->enum('status', [
                'pending',     // створили форму, ще не прийшов холд
                'authorized',  // гроші заблоковано
                'matched',     // знайшли місця, готуємо бронювання/захоплення
                'captured',    // захопили/оплатили і видали квитки
                'voided',      // холд скасовано
                'refunded',    // (на випадок якщо вже захопили і повернули)
                'expired',     // авто-закриття (не встигли до T-12h, скасували холд)
                'cancelled',   // користувач скасував
            ])->default('pending');

            $t->timestamp('authorized_at')->nullable();
            $t->timestamp('matched_at')->nullable();
            $t->timestamp('captured_at')->nullable();
            $t->timestamp('voided_at')->nullable();

            // до якого дедлайну чекаємо місця: min(trip_date-12h, термін дії холду у W4P)
            $t->timestamp('wait_until')->nullable();

            // зв’язок із бронюванням(ями) якщо спрацювало
            $t->json('booking_ids')->nullable(); // [ids]
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('standby_requests');
    }
};
