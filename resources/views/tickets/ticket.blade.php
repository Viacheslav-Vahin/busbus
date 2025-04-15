<!DOCTYPE html>
<html>
<head>
    <title>Квиток #{{ $booking->id }}</title>
</head>
<body>
<h1>Квиток #{{ $booking->id }}</h1>
<p>Подорож: {{ $booking->trip->start_location }} - {{ $booking->trip->end_location }}</p>
<p>Місце: {{ $booking->seat_number }}</p>
<p>Ціна: ${{ $booking->price }}</p>
<p>Додаткові послуги: {{ implode(', ', $booking->additional_services) }}</p>
<p>Проскануй QR код для підтвердження подорожі:</p>
<img src="data:image/png;base64, {!! base64_encode(QrCode::format('png')->size(200)->generate(route('trip.confirm', $booking->id))) !!} ">
</body>
</html>
