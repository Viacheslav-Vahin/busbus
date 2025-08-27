<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <style>
        body, table, th, td, h1, h2, h3 {
            font-family: "DejaVu Sans", DejaVuSans, sans-serif;
            font-size: 12px;
        }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; }
    </style>
</head>
<body>
<h2>Звіт по продажах квитків</h2>
<table width="100%" border="1" cellspacing="0" cellpadding="5">
    <thead>
    <tr>
        <th>Дата</th>
        <th>Маршрут</th>
        <th>Автобус</th>
        <th>Місце</th>
        <th>Пасажир</th>
        <th>Сума</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($bookings as $booking)
        <tr>
            <td>{{ \Illuminate\Support\Carbon::parse($booking->date)->format('Y-m-d') }}</td>
            <td>{{ $booking->route_display ?? '—' }}</td>
            <td>{{ optional($booking->bus)->name ?? '—' }}</td>
            <td>{{ $booking->selected_seat ?? $booking->seat_number ?? '—' }}</td>

            {{-- 1) беремо з passengers; 2) якщо порожньо – ім'я користувача; 3) інакше тире --}}
            <td>{{ $booking->passengerNames ?: optional($booking->user)->name ?: '—' }}</td>

            <td>{{ number_format((float) $booking->price, 2, '.', ' ') }}</td>
        </tr>
    @endforeach
    </tbody>

</table>


</body>
</html>
