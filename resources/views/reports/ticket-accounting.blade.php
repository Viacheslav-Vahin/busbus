<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Бухгалтерський звіт по квитку</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1,h2,h3 { margin: 0 0 6px; }
        .muted { color: #555; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border:1px solid #000; padding:6px; text-align:left; vertical-align: top; }
        th { background:#f3f3f3; }
        .right { text-align: right; }
        .mt8 { margin-top: 8px; } .mt16{ margin-top:16px; } .mt24{ margin-top:24px; }
    </style>
</head>
<body>
@php($c = $company)
<h2>{{ $c['name'] }}</h2>
<div class="muted">
    ЄДРПОУ: {{ $c['edrpou'] }} &nbsp;|&nbsp;
    Адреса: {{ $c['addr'] }}<br>
    IBAN: {{ $c['iban'] }} ({{ $c['bank'] }}) &nbsp;|&nbsp; {{ $c['vat'] }}
</div>

<h3 class="mt16">Акт/квитанція по бронюванню № {{ $b->id }}</h3>
<div class="muted">Дата операції: {{ \Carbon\Carbon::parse($b->date)->format('Y-m-d') }}</div>

<table class="mt16">
    <tr>
        <th>Маршрут</th>
        <td>{{ $b->route_display }}</td>
        <th>Автобус</th>
        <td>{{ optional($b->bus)->name ?? '—' }}</td>
    </tr>
    <tr>
        <th>Місце</th>
        <td>{{ $b->selected_seat }}</td>
        <th>Пасажир</th>
        <td>
            {{ $b->passengerNames ?: '—' }}<br>
            <span class="muted">
                {{ $b->passengerPhone ?: '' }}
                @if($b->passengerEmail) · {{ $b->passengerEmail }} @endif
            </span>
        </td>
    </tr>
    <tr>
        <th>Метод оплати</th>
        <td>{{ $b->payment_method ?? '—' }}</td>
        <th>Статус</th>
        <td>{{ $b->status ?? '—' }} @if($b->paid_at) ({{ $b->paid_at }}) @endif</td>
    </tr>
</table>

<table class="mt16">
    <thead>
    <tr>
        <th>Послуга</th>
        <th class="right">К-сть</th>
        <th class="right">Ціна, грн</th>
        <th class="right">Сума, грн</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td>Квиток на автобус ({{ $b->route_display }})</td>
        <td class="right">1</td>
        <td class="right">{{ number_format((float)$b->price, 2, ',', ' ') }}</td>
        <td class="right">{{ number_format((float)$b->price, 2, ',', ' ') }}</td>
    </tr>

    @forelse($additionalServices as $srv)
        <tr>
            <td>Додаткова послуга: {{ $srv->name }}</td>
            <td class="right">1</td>
            <td class="right">{{ number_format((float) $srv->price, 2, ',', ' ') }}</td>
            <td class="right">{{ number_format((float) $srv->price, 2, ',', ' ') }}</td>
        </tr>
    @empty
        {{-- без додаткових послуг --}}
    @endforelse

    </tbody>
    <tfoot>
    <tr>
        <th colspan="3" class="right">Разом, грн</th>
        <th class="right">{{ number_format((float) $grandTotal, 2, ',', ' ') }}</th>
    </tr>
    </tfoot>
</table>

<p class="mt24 muted">
    Документ згенеровано автоматично системою бронювання MaxBus. Не потребує підпису.
</p>
</body>
</html>
