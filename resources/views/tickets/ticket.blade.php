{{-- resources/views/tickets/pdf.blade.php --}}
    <!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <title>Електронний квиток • {{ $booking->ticket_serial }}</title>
    <style>
        @font-face { font-family: 'DejaVu'; src: url('{{ storage_path("fonts/DejaVuSans.ttf") }}') format('truetype'); font-weight: normal; }
        @font-face { font-family: 'DejaVu'; src: url('{{ storage_path("fonts/DejaVuSans-Bold.ttf") }}') format('truetype'); font-weight: bold; }
        * { font-family: 'DejaVu', 'DejaVu Sans', sans-serif; }
        body { font-size: 12pt; }
        .box { border:1px solid #ccc; padding:14px; }
        .grid { width:100%; border-collapse:collapse; }
        .cell-r { width:36%; border-left:1px solid #ccc; text-align:center; vertical-align:middle; }
        .muted { color:#666; }
    </style>
</head>
<body>

<h1>Електронний квиток • {{ $booking->ticket_serial }}</h1>

<table class="grid">
    <tr>
        <td style="width:64%; vertical-align:top;">
            <div class="box">
                <p><b>Маршрут:</b> {{ $booking->route?->start_point }} - {{ $booking->route?->end_point }}</p>
                <p><b>Дата/час:</b> {{ \Carbon\Carbon::parse($booking->date)->format('d.m.Y') }} @ {{ $booking->trip->departure_time ?? '' }}</p>
                <p><b>Автобус:</b> {{ $booking->bus?->name }}</p>
                <p><b>Місце:</b> №{{ $booking->seat_number }}</p>
                <p><b>Пасажир(и):</b> {{ $booking->passengerNames }}</p>
                <p><b>Контакти:</b> {{ $booking->passengerPhone }} • {{ $booking->passengerEmail }}</p>
                <p><b>Сума:</b> {{ number_format($booking->price, 2, ',', ' ') }} {{ $booking->currency->code ?? $booking->currency_code }}</p>
                <p class="muted">№ бронювання: {{ $booking->id }} • Статус: {{ $booking->status }}</p>
            </div>
        </td>
        <td class="cell-r">
            <div style="font-weight:bold; margin-bottom:6px;">QR</div>

            {{-- Пріоритет 1: inline SVG (найнадійніше для DomPDF) --}}
            @isset($qrSvg)
                {!! $qrSvg !!}
            @elseif(!empty($qrPublicUrl))
                {{-- Пріоритет 2: публічний URL --}}
                <img src="{{ $qrPublicUrl }}" alt="QR" style="width:200px; height:200px;">
            @elseif(!empty($qrPath))
                {{-- Пріоритет 3: локальний шлях --}}
                <img src="file://{{ $qrPath }}" alt="QR" style="width:200px; height:200px;">
            @else
                <div class="muted">QR недоступний</div>
            @endisset

            <div style="margin-top:8px;">Покажіть QR при посадці</div>
        </td>
    </tr>
</table>

<p class="muted" style="margin-top:14px;">
    Повернення/умови: https://maxbus.com.ua/info/
</p>

</body>
</html>
