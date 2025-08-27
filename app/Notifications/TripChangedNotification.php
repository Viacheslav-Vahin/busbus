<?php
// app/Notifications/TripReminderNotification.php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class TripReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public $booking, public string $window = '24h') {}

    public function via($notifiable)
    {
        return ['mail','database']; // + свої: sms, viber, telegram (див. нижче)
    }

    public function toMail($notifiable)
    {
        $b = $this->booking;
        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject('Змінився час/автобус для вашої поїздки')
            ->greeting('Вітаємо!')
            ->line("Рейс: {$b->route_display}")
            ->line('Дата: '.\Carbon\Carbon::parse($b->date)->format('d.m.Y').' '.$b->trip->departure_time)
            ->line('Місце: №'.$b->seat_number)
            ->line('Статус: '.$b->status)
            ->line('Прибуття завчасно, при посадці покажіть QR-квиток.');
    }

    public function toArray($notifiable)
    {
        return ['booking_id' => $this->booking->id, 'window' => $this->window];
    }
}
