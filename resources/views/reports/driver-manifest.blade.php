<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <title>Маніфест водія</title>
    <style>
        body{font-family: DejaVu Sans, sans-serif; font-size: 12px; color:#111}
        h1{font-size: 20px; margin:0 0 8px}
        table{width:100%; border-collapse: collapse; margin-top:10px}
        th,td{border:1px solid #ccc; padding:6px 8px; vertical-align: top}
        th{background:#f3f4f6; text-align:left}
        .muted{color:#6b7280}
        .right{text-align:right}
    </style>
</head>
<body>
<h1>Маніфест водія</h1>
<div>Дата: <b>{{ $date }}</b></div>
<div>Автобус: <b>{{ $bus->name }}</b></div>
<div>Маршрут: <b>{{ $route }}</b></div>

<table>
    <thead>
    <tr>
        <th style="width:50px">Місце</th>
        <th>Пасажир / Телефон</th>
        <th style="width:95px">Оплата</th>
        <th style="width:95px" class="right">Сума</th>
        <th style="width:80px">Посадка</th>
        <th>Примітки</th>
    </tr>
    </thead>
    <tbody>
    @foreach($rows as $r)
        <tr>
            <td>{{ $r['seat'] }}</td>
            <td>
                {{ $r['name'] ?: '—' }}<br>
                @if($r['phone']) <span class="muted">{{ $r['phone'] }}</span><br>@endif
                <span class="muted">UUID: {{ $r['uuid'] }}</span>
            </td>
            <td>{{ $r['paidLabel'] }}</td>
            <td class="right">
                {{ number_format($r['price_uah'], 2, '.', ' ') }} UAH
                @if(($r['currency'] ?? 'UAH') !== 'UAH' && ($r['price_fx'] ?? 0) > 0)
                    <div class="muted">~ {{ number_format($r['price_fx'], 2, '.', ' ') }} {{ $r['currency'] }}</div>
                @endif
            </td>
            <td>{{ $r['boarded_at'] ?: '' }}</td>
            <td>
                @if($r['extras']) <div>Додатково: {{ $r['extras'] }}</div>@endif
                @if($r['note'])   <div>Коментар: {{ $r['note'] }}</div>@endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>

<p style="margin-top:8px">
    Всього пасажирів: <b>{{ $totals['count'] }}</b><br>
    Оплачено завчасно: <b>{{ number_format($totals['paid_uah'], 2, '.', ' ') }} UAH</b><br>
    До інкасації на борту: <b>{{ number_format($totals['unpaid_uah'], 2, '.', ' ') }} UAH</b>
</p>
</body>
</html>
