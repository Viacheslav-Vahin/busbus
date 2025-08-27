<?php
// app/Console/Commands/SendTripReminders.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\{Booking, NotificationLog, UserNotificationSetting};
use App\Notifications\TripReminderNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\{Mail, Log};

class SendTripReminders extends Command
{
    protected $signature = 'reminders:trips';
    protected $description = 'Надсилати нагадування пасажирам (24h/2h)';

    public function handle(): int
    {
        $now = now();
        $window24 = [$now->copy()->addDay()->subMinutes(5), $now->copy()->addDay()->addMinutes(5)];
        $window2  = [$now->copy()->addHours(2)->subMinutes(5), $now->copy()->addHours(5)]; // трохи ширше, якщо cron спізнився

        $bookings = Booking::with(['trip','route','user'])
            ->whereIn('status', ['paid'])             // лише оплачені
            ->whereDate('date', '>=', $now->toDateString())
            ->get();

        $sent = 0;

        foreach ($bookings as $b) {
            if (!$b->trip?->departure_time) continue;
            $departAt = Carbon::parse($b->date.' '.$b->trip->departure_time, config('app.timezone','UTC'));

            // 24 години
            if (empty($b->reminder_24h_sent_at) && $departAt->between(...$window24)) {
                $sent += $this->sendForBooking($b, '24h', $departAt) ? 1 : 0;
            }
            // 2 години
            if (empty($b->reminder_2h_sent_at) && $departAt->between(...$window2)) {
                $sent += $this->sendForBooking($b, '2h', $departAt) ? 1 : 0;
            }
        }

        $this->info("Reminders sent: {$sent}");
        return self::SUCCESS;
    }

    protected function sendForBooking(Booking $b, string $kind, Carbon $departAt): bool
    {
        $user = $b->user;
        $settings = UserNotificationSetting::firstOrCreate(['user_id'=>$user->id]);

        $atNight = (int)now()->format('H') < 8 || (int)now()->format('H') >= 22; // quiet-hours 22:00–08:00
        $anySent = false;

        // 1) E-mail (допускаємо надсилання у будь-який час)
        if ($settings->email_enabled && ($user->email || $b->passengerEmail)) {
            try {
                $user->notify(new TripReminderNotification($b, $kind, $departAt));
                NotificationLog::create([
                    'type'=>"trip_reminder_{$kind}",
                    'channel'=>'email',
                    'booking_id'=>$b->id,
                    'to'=>$user->email ?? $b->passengerEmail,
                    'status'=>'sent',
                    'meta'=>['depart_at'=>$departAt->toIso8601String()],
                ]);
                $anySent = true;
            } catch (\Throwable $e) {
                NotificationLog::create([
                    'type'=>"trip_reminder_{$kind}", 'channel'=>'email', 'booking_id'=>$b->id,
                    'to'=>$user->email ?? $b->passengerEmail, 'status'=>'error', 'meta'=>['error'=>$e->getMessage()],
                ]);
            }
        }

        // 2) Viber / Telegram / SMS — поважаємо quiet-hours
        if (!$atNight) {
            // Viber
            if ($settings->viber_enabled && class_exists(\App\Services\ViberSender::class) && ($b->passengerPhone ?? $user->phone)) {
                try {
                    $msg = $this->text($b, $kind, $departAt);
                    \App\Services\ViberSender::sendInvoice($b->passengerPhone ?? $user->phone, $msg);
                    NotificationLog::create(['type'=>"trip_reminder_{$kind}", 'channel'=>'viber','booking_id'=>$b->id,'to'=>$b->passengerPhone ?? $user->phone,'status'=>'sent']);
                    $anySent = true;
                } catch (\Throwable $e) {
                    NotificationLog::create(['type'=>"trip_reminder_{$kind}", 'channel'=>'viber','booking_id'=>$b->id,'to'=>$b->passengerPhone ?? $user->phone,'status'=>'error','meta'=>['error'=>$e->getMessage()]]);
                }
            }
            // Telegram
            if ($settings->telegram_enabled && class_exists(\App\Services\TelegramSender::class) && $b->passengerTelegram) {
                try {
                    \App\Services\TelegramSender::sendInvoice($b->passengerTelegram, $this->text($b,$kind,$departAt));
                    NotificationLog::create(['type'=>"trip_reminder_{$kind}", 'channel'=>'telegram','booking_id'=>$b->id,'to'=>$b->passengerTelegram,'status'=>'sent']);
                    $anySent = true;
                } catch (\Throwable $e) {
                    NotificationLog::create(['type'=>"trip_reminder_{$kind}", 'channel'=>'telegram','booking_id'=>$b->id,'to'=>$b->passengerTelegram,'status'=>'error','meta'=>['error'=>$e->getMessage()]]);
                }
            }
            // SMS — якщо буде інтеграція
        } else {
            Log::info('Quiet-hours: messengers skipped', ['booking'=>$b->id,'kind'=>$kind]);
        }

        // позначка в booking — після хоча б одного успішного каналу
        if ($anySent) {
            $col = $kind === '24h' ? 'reminder_24h_sent_at' : 'reminder_2h_sent_at';
            $b->forceFill([$col => now()])->saveQuietly();
        }

        return $anySent;
    }

    protected function text(Booking $b, string $kind, Carbon $departAt): string
    {
        $route = $b->route_display ?? ($b->route?->start_point.' — '.$b->route?->end_point);
        $seats = $b->selected_seat ?: $b->seat_number;
        $seats = is_array($seats) ? implode(', ', $seats) : $seats;

        return "🔔 Нагадування ({$kind})\n"
            ."🚌 {$route}\n"
            ."📅 ".$departAt->format('d.m.Y H:i')."\n"
            ."💺 Місце(ця): {$seats}\n"
            ."№ замовлення: {$b->order_id}\n"
            ."ℹ️ https://".parse_url(config('app.url'), PHP_URL_HOST)."/profile/orders";
    }
}
