<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <title>Електронний квиток • {{ $b->ticket_serial }}</title>
    <style>
        @font-face { font-family:'DejaVu'; src:url('{{ storage_path("fonts/DejaVuSans.ttf") }}') format('truetype'); font-weight:normal; font-style:normal; }
        @font-face { font-family:'DejaVu'; src:url('{{ storage_path("fonts/DejaVuSans-Bold.ttf") }}') format('truetype'); font-weight:bold;   font-style:normal; }

        * { font-family:'DejaVu','DejaVu Sans',sans-serif; }
        body { font-size:12pt; margin: 24px; }
        h3 { font-weight:bold; margin: 0 0 14px; }

        /* Табличний двоколонковий макет — стабільний для Dompdf */
        .row   { display: table; width: 100%; border-collapse: collapse; }
        .col-l { display: table-cell; width: 64%; vertical-align: top; padding-right: 16px; }
        .col-r { display: table-cell; width: 36%; vertical-align: middle; padding-left: 16px; border-left: 1px solid #ccc; text-align: center; }

        .box   { border:1px solid #ccc; padding:14px; }
        .muted { color:#666; }
    </style>
</head>
<body>

<h3>Електронний квиток • {{ $b->ticket_serial }}</h3>

<div class="row">
    <!-- Ліва колонка: деталі -->
    <div class="col-l">
        <div class="box">
            <p><b>Маршрут:</b> {{ $b->route?->start_point }} - {{ $b->route?->end_point }}</p>
            <p><b>Дата/час:</b>
                {{ \Carbon\Carbon::parse($b->date)->format('d.m.Y') }}
                @ {{ $b->trip->departure_time ?? '' }}
            </p>
            <p><b>Автобус:</b> {{ $b->bus?->name }}</p>
            <p><b>Місце:</b> №{{ $b->seat_number }}</p>
            <p><b>Пасажир(и):</b> {{ $b->passengerNames }}</p>
            <p><b>Контакти:</b> {{ $b->passengerPhone }} • {{ $b->passengerEmail }}</p>
            <p><b>Сума:</b> {{ number_format($b->price, 2, ',', ' ') }} {{ $b->currency->code ?? $b->currency_code }}</p>
            <p class="muted">№ бронювання: {{ $b->id }} • Статус: {{ $b->status }}</p>
        </div>
    </div>

    <!-- Права колонка: QR -->
    <div class="col-r">
        <div style="font-weight:bold; margin-bottom:6px;">QR</div>
        <img src="{{ $qrDataUri }}" alt="QR" width="220" height="220" style="display:block;margin:10px auto;" />
        <div>Покажіть QR при посадці</div>
    </div>
</div>

<p class="muted" style="margin-top:14px;">
    Повернення/умови: https://maxbus.com.ua/info/
</p>

</body>
</html>
