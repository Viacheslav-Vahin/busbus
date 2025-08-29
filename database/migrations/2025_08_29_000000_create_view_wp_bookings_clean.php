<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE VIEW wp_bookings_clean AS
SELECT
  id, wp_id, wp_order_id, wp_bus_id, bus_id,
  passenger_name,
  NULLIF(TRIM(LOWER(passenger_email)), '') AS passenger_email_effective,
  passenger_email,
  passenger_phone,
  booked_by_name,
  LOWER(booked_by_email) AS booked_by_email,
  booked_by_phone,
  booked_by_user_id,
  user_id,
  CASE
    WHEN booked_by_is_manager = 1 THEN 'manager'
    WHEN booked_by_is_third_party = 1 THEN 'third_party'
    ELSE 'self'
  END AS booker_type,
  booked_by_is_manager,
  booked_by_is_third_party,
  boarding_point, boarding_time, dropping_point, dropping_time,
  start_time, DATE(start_time)  AS start_date,
  booking_date, DATE(booking_date) AS booking_day,
  ticket_type, seat_number, fare, total_price, payment_method,
  order_status, ticket_status,
  attendee_info, extra_services, meta,
  ( (passenger_email IS NOT NULL AND passenger_email <> '')
    OR (passenger_phone IS NOT NULL AND passenger_phone <> '') ) AS has_contact,
  created_at, updated_at
FROM wp_bookings_raw;
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP VIEW IF EXISTS wp_bookings_clean');
    }
};
