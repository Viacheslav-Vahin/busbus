<!-- resources/views/mail/ticket_paid.blade.php -->
<p>Вітаємо, {{ $booking->user->name }}!</p>
<p>Оплату отримано. У додатку — ваш PDF-квиток.</p>
<ul>
    <li>Маршрут: {{ optional($booking->trip->route)->title ?? '—' }}</li>
    <li>Дата: {{ \Illuminate\Support\Carbon::parse($booking->date)->format('d.m.Y') }}</li>
    <li>Місця: {{ $booking->selected_seat }}</li>
    <li>Сума: {{ number_format($booking->price, 2, '.', ' ') }} UAH</li>
</ul>
<p>Гарної подорожі!</p>
