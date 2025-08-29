<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('wp_bookings_raw', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('wp_id')->unique();                 // wp post_id (wbtm_bus_booking)
            $t->unsignedBigInteger('wp_order_id')->nullable();         // wbtm_order_id (Woo order post_id)
            $t->unsignedBigInteger('wp_bus_id')->nullable();           // wbtm_bus_id
            $t->unsignedBigInteger('bus_id')->nullable();              // наш bus.id (коли знайдемо по buses.wp_id)

            // пасажир/бронювання
            $t->string('passenger_name')->nullable();                  // wbtm_user_name або attendee full_name
            $t->string('passenger_email')->nullable();                 // wbtm_user_email або attendee email
            $t->string('passenger_phone', 64)->nullable();             // wbtm_user_phone або attendee phone (очищений)
            $t->unsignedBigInteger('user_id')->nullable();             // наш users.id (знайдений по email/phone)

            // маршрут/час
            $t->string('boarding_point')->nullable();
            $t->dateTime('boarding_time')->nullable();
            $t->string('dropping_point')->nullable();
            $t->dateTime('dropping_time')->nullable();
            $t->dateTime('start_time')->nullable();                    // wbtm_start_time
            $t->dateTime('booking_date')->nullable();                  // wbtm_booking_date (коли створили бронь)

            // місце/квиток/суми/оплата/статуси
            $t->string('ticket_type')->nullable();                     // wbtm_ticket (Дорослий/…)
            $t->string('seat_number', 16)->nullable();                 // wbtm_seat
            $t->decimal('fare', 12, 2)->nullable();                    // wbtm_bus_fare
            $t->decimal('total_price', 12, 2)->nullable();             // wbtm_tp (сума за бронювання)
            $t->string('payment_method')->nullable();                  // wbtm_billing_type або з Woo
            $t->string('order_status')->nullable();                    // completed/failed/…
            $t->string('ticket_status')->nullable();                   // 1/0 → active/cancelled

            // сирі мета/запасні поля
            $t->json('attendee_info')->nullable();                     // розпарсений serialized масив
            $t->json('extra_services')->nullable();
            $t->json('meta')->nullable();                              // будь-які інші крихти
            $t->timestamps();

            $t->index(['wp_bus_id']);
            $t->index(['passenger_email']);
            $t->index(['passenger_phone']);
            $t->index(['start_time']);
            $t->index(['order_status']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('wp_bookings_raw');
    }
};
