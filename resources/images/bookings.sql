-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1:3306
-- Время создания: Сен 30 2025 г., 15:20
-- Версия сервера: 11.8.3-MariaDB-log
-- Версия PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `u303756778_bus_booking_db`
--

-- --------------------------------------------------------

--
-- Структура таблицы `bookings`
--

CREATE TABLE `bookings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `route_id` bigint(20) UNSIGNED DEFAULT NULL,
  `destination_id` bigint(20) UNSIGNED DEFAULT NULL,
  `trip_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `bus_id` bigint(20) UNSIGNED NOT NULL,
  `selected_seat` varchar(255) DEFAULT NULL,
  `passengers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`passengers`)),
  `date` date DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `seat_number` int(11) NOT NULL,
  `price` decimal(8,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `promo_code` varchar(255) DEFAULT NULL,
  `price_uah` decimal(10,2) DEFAULT NULL,
  `additional_services` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`additional_services`)),
  `pricing` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`pricing`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `currency_code` varchar(255) NOT NULL DEFAULT 'UAH',
  `fx_rate` decimal(12,6) DEFAULT NULL,
  `status` enum('hold','pending','paid','expired','cancelled','refunded') NOT NULL DEFAULT 'hold',
  `agent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `cancel_responsibility` enum('agent','carrier') DEFAULT NULL,
  `payment_meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payment_meta`)),
  `paid_at` timestamp NULL DEFAULT NULL,
  `reminder_24h_sent_at` timestamp NULL DEFAULT NULL,
  `reminder_2h_sent_at` timestamp NULL DEFAULT NULL,
  `payment_method` varchar(255) DEFAULT NULL,
  `invoice_number` varchar(255) DEFAULT NULL,
  `tax_rate` decimal(5,2) DEFAULT NULL,
  `ticket_uuid` char(36) DEFAULT NULL,
  `ticket_rev` varchar(20) DEFAULT NULL,
  `qr_path` varchar(255) DEFAULT NULL,
  `ticket_pdf_path` varchar(255) DEFAULT NULL,
  `checked_in_at` timestamp NULL DEFAULT NULL,
  `checked_in_by` bigint(20) UNSIGNED DEFAULT NULL,
  `checkin_place` varchar(255) DEFAULT NULL,
  `ticket_serial` varchar(255) DEFAULT NULL,
  `order_id` char(36) DEFAULT NULL,
  `hold_token` char(40) DEFAULT NULL,
  `held_until` timestamp NULL DEFAULT NULL,
  `is_solo_companion` tinyint(1) NOT NULL DEFAULT 0,
  `discount_pct` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `bookings`
--

INSERT INTO `bookings` (`id`, `route_id`, `destination_id`, `trip_id`, `bus_id`, `selected_seat`, `passengers`, `date`, `user_id`, `seat_number`, `price`, `discount_amount`, `promo_code`, `price_uah`, `additional_services`, `pricing`, `created_at`, `updated_at`, `currency_code`, `fx_rate`, `status`, `agent_id`, `cancel_responsibility`, `payment_meta`, `paid_at`, `reminder_24h_sent_at`, `reminder_2h_sent_at`, `payment_method`, `invoice_number`, `tax_rate`, `ticket_uuid`, `ticket_rev`, `qr_path`, `ticket_pdf_path`, `checked_in_at`, `checked_in_by`, `checkin_place`, `ticket_serial`, `order_id`, `hold_token`, `held_until`, `is_solo_companion`, `discount_pct`) VALUES
(11493, 1, NULL, 325, 74, '8', '[{\"first_name\":\"Moisieienkova\",\"last_name\":\"Tetiana\",\"doc_number\":null,\"category\":\"adult\",\"email\":null,\"phone_number\":null,\"note\":null}]', '2024-11-21', NULL, 8, 2200.00, 0.00, NULL, NULL, NULL, NULL, '2025-09-05 16:21:49', '2025-09-05 16:21:49', 'UAH', NULL, 'paid', 10, NULL, NULL, NULL, NULL, NULL, NULL, 'wp:39330', NULL, '9668e1d0-d06f-4390-8f7a-fab4b50e4bbd', NULL, NULL, NULL, NULL, NULL, NULL, 'MAX-2025-011493', 'wp-order-39329', NULL, NULL, 0, NULL);

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bookings_ticket_uuid_unique` (`ticket_uuid`),
  ADD UNIQUE KEY `bookings_hold_token_unique` (`hold_token`),
  ADD KEY `bookings_trip_id_foreign` (`trip_id`),
  ADD KEY `bookings_user_id_foreign` (`user_id`),
  ADD KEY `bookings_bus_id_foreign` (`bus_id`),
  ADD KEY `bookings_route_id_foreign` (`route_id`),
  ADD KEY `bookings_paid_at_index` (`paid_at`),
  ADD KEY `bookings_ticket_serial_index` (`ticket_serial`),
  ADD KEY `bookings_checked_in_by_foreign` (`checked_in_by`),
  ADD KEY `bookings_ticket_uuid_rev_idx` (`ticket_uuid`,`ticket_rev`),
  ADD KEY `bookings_order_id_index` (`order_id`),
  ADD KEY `bookings_held_until_index` (`held_until`),
  ADD KEY `idx_bookings_bus_date` (`bus_id`,`date`),
  ADD KEY `bookings_cancel_responsibility_index` (`cancel_responsibility`),
  ADD KEY `bookings_agent_id_index` (`agent_id`),
  ADD KEY `idx_bookings_report_filters` (`date`,`status`,`currency_code`,`agent_id`,`payment_method`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19845;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_agent_id_foreign` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `bookings_bus_id_foreign` FOREIGN KEY (`bus_id`) REFERENCES `buses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_checked_in_by_foreign` FOREIGN KEY (`checked_in_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `bookings_route_id_foreign` FOREIGN KEY (`route_id`) REFERENCES `routes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_trip_id_foreign` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `bookings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
