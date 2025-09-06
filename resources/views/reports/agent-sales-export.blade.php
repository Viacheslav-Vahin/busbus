<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #444; padding: 4px 6px; }
        th { background: #eee; }
        .mb-8 { margin-bottom: 16px; }
        .no-border td, .no-border th { border: none; }
    </style>
</head>
<body>
<h3>Звіт з проданих квиткiв за період {{ $filters['from'] }}-{{ $filters['to'] }}</h3>
<div>Агентський договір №{{ $meta['contract_no'] }} від {{ $meta['contract_date'] }}</div>
<div>АГЕНТ: {{ $meta['agent_name'] }}</div>
<div class="mb-8">ПЕРЕВІЗНИК: {{ $meta['carrier_name'] }}</div>

<table class="mb-8">
    <thead>
    <tr>
        <th>Продано</th>
        <th>Повернено</th>
        <th>Утриманий при поверненні</th>
        <th>Підсумкова сума</th>
        <th>Винагорода АГЕНТА</th>
        <th>До виплати ПЕРЕВІЗНИКУ</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td>{{ number_format($totals['soldTotal'],2,'.',' ') }} {{ $filters['currency'] }}</td>
        <td>{{ number_format($totals['returnedTotal'],2,'.',' ') }} {{ $filters['currency'] }}</td>
        <td>{{ number_format($totals['retainedTotal'],2,'.',' ') }} {{ $filters['currency'] }}</td>
        <td>{{ number_format($totals['subtotal'],2,'.',' ') }} {{ $filters['currency'] }}</td>
        <td>{{ number_format($totals['agentReward'],2,'.',' ') }} {{ $filters['currency'] }}</td>
        <td>{{ number_format($totals['toCarrier'],2,'.',' ') }} {{ $filters['currency'] }}</td>
    </tr>
    </tbody>
</table>

<h4>Продані квитки ({{ $filters['currency'] }})</h4>
<table class="mb-8">
    <thead>
    <tr>
        <th>№</th><th>№ квитка</th><th>Пасажир</th><th>Напрямок</th>
        <th>Дата відправлення</th><th>Відсоток Агента</th><th>Ціна</th>
        <th>Винагорода АГЕНТА</th><th>До виплати ПЕРЕВІЗНИКУ</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($sold as $i => $row)
        <tr>
            <td>{{ $i+1 }}</td>
            <td>{{ $row['ticket_no'] }}</td>
            <td>{{ $row['passenger'] }}</td>
            <td>{{ $row['direction'] }}</td>
            <td>{{ $row['date'] }}</td>
            <td>{{ rtrim(rtrim(number_format($row['agent_pct'], 2, '.', ''), '0'), '.') }}</td>
            <td>{{ number_format($row['price'],2,'.',' ') }}</td>
            <td>{{ number_format($row['agent_fee'],2,'.',' ') }}</td>
            <td>{{ number_format($row['to_carrier'],2,'.',' ') }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<h4>Скасовані квитки в рамках договірної відповідальності АГЕНТА</h4>
<table class="mb-8">
    <thead>
    <tr>
        <th>№</th><th>№ квитка</th><th>Пасажир</th><th>Напрямок</th>
        <th>Дата відправлення</th><th>Відсоток (утримання)</th>
        <th>Ціна квитка</th><th>Винагорода АГЕНТА</th><th>До виплати ПЕРЕВІЗНИКУ</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($canceled as $i => $row)
        <tr>
            <td>{{ $i+1 }}</td>
            <td>{{ $row['ticket_no'] }}</td>
            <td>{{ $row['passenger'] }}</td>
            <td>{{ $row['direction'] }}</td>
            <td>{{ $row['date'] }}</td>
            <td>{{ rtrim(rtrim(number_format($row['retention_pct'], 2, '.', ''), '0'), '.') }}</td>
            <td>{{ number_format($row['price'],2,'.',' ') }}</td>
            <td>{{ number_format($row['agent_from_retention'],2,'.',' ') }}</td>
            <td>{{ number_format($row['carrier_from_retention'],2,'.',' ') }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="no-border">
    <tr>
        <td width="50%">
            АГЕНТ: {{ $meta['agent_name'] }}<br><br>
            Підпис ____________________<br>
            ПІБ _______________________
        </td>
        <td width="50%">
            ПЕРЕВІЗНИК: {{ $meta['carrier_name'] }}<br><br>
            Підпис ____________________<br>
            ПІБ _______________________
        </td>
    </tr>
</table>
</body>
</html>
