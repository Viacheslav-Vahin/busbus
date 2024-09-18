<!DOCTYPE html>
<html>
<head>
    <title>Ticket #{{ $booking->id }}</title>
</head>
<body>
<h1>Ticket #{{ $booking->id }}</h1>
<p>Trip: {{ $booking->trip->start_location }} - {{ $booking->trip->end_location }}</p>
<p>Seat: {{ $booking->seat_number }}</p>
<p>Price: ${{ $booking->price }}</p>
<p>Additional Services: {{ implode(', ', $booking->additional_services) }}</p>
<p>Scan the QR code to confirm your trip:</p>
<img src="data:image/png;base64, {!! base64_encode(QrCode::format('png')->size(200)->generate(route('trip.confirm', $booking->id))) !!} ">
</body>
</html>
