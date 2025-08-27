<?php
// app/Notifications/TripReminderNotification.php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Booking;
use Carbon\Carbon;

class TripReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking, public string $kind /* '24h'|'2h' */, public Carbon $departAt) {}

    public function via($notifiable): array
    {
        return ['mail','database']; // e-mail + запис у notifications
    }

    public function toMail($notifiable): MailMessage
    {
        $b = $this->booking;
        $route = $b->route_display ?? ($b->route?->start_point.' — '.$b->route?->end_point);
        $seats = is_array($b->passengers) ? ($b->selected_seat ?? $b->seat_number) : $b->seat_number;

        return (new MailMessage)
            ->subject("Нагадування про поїздку ({$this->kind})")
            ->greeting('Вітаємо!')
            ->line("Маршрут: {$route}")
            ->line('Дата та час: '.$this->departAt->format('d.m.Y H:i'))
            ->line('Місце(ця): '.$seats)
            ->line('Номер замовлення: '.$b->order_id)
            ->action('Інформація', url('/profile/orders'))
            ->line('Гарної подорожі!');
    }

    public function toArray($notifiable): array
    {
        return ['booking_id'=>$this->booking->id,'kind'=>$this->kind,'depart_at'=>$this->departAt->toIso8601String()];
    }
}

