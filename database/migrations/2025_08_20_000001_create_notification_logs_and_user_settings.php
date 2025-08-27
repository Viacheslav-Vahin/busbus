<?php
// database/migrations/2025_08_20_000001_create_notification_logs_and_user_settings.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('notification_logs', function (Blueprint $t) {
            $t->id();
            $t->string('type');                 // e.g. trip_reminder_24h, trip_reminder_2h, payment_link
            $t->string('channel');              // email|sms|viber|telegram
            $t->unsignedBigInteger('booking_id')->nullable()->index();
            $t->uuid('order_id')->nullable()->index();
            $t->string('to')->nullable();       // email / phone / @tg
            $t->string('status')->default('sent'); // sent|error
            $t->json('meta')->nullable();       // payload / error
            $t->timestamps();
        });

        Schema::create('user_notification_settings', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->unique();
            $t->boolean('email_enabled')->default(true);
            $t->boolean('sms_enabled')->default(false);
            $t->boolean('viber_enabled')->default(true);
            $t->boolean('telegram_enabled')->default(false);
            $t->string('lang', 5)->default('uk');
            $t->timestamps();
        });

        // (якщо ще не додавали) позначки «нагадування надіслано»
        if (!Schema::hasColumn('bookings','reminder_24h_sent_at')) {
            Schema::table('bookings', function (Blueprint $t) {
                $t->timestamp('reminder_24h_sent_at')->nullable()->after('paid_at');
                $t->timestamp('reminder_2h_sent_at')->nullable()->after('reminder_24h_sent_at');
            });
        }
    }

    public function down(): void {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('user_notification_settings');
        Schema::table('bookings', function (Blueprint $t) {
            if (Schema::hasColumn('bookings','reminder_24h_sent_at')) $t->dropColumn('reminder_24h_sent_at');
            if (Schema::hasColumn('bookings','reminder_2h_sent_at'))  $t->dropColumn('reminder_2h_sent_at');
        });
    }
};
