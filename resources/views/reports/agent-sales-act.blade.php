<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1, h2 { text-align:center; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .mt-2{ margin-top:8px; } .mt-4{ margin-top:16px; } .mt-6{ margin-top:24px; }
        .mb-2{ margin-bottom:8px; } .mb-4{ margin-bottom:16px; }
        .center { text-align:center; }
        .right { text-align:right; }
        .grid2 { width:100%; }
        .grid2 td { vertical-align:top; width:50%; }
        th,td { padding:6px; }
        .b { font-weight:bold; }
        .tbl th, .tbl td { border:1px solid #222; }
        .small { font-size: 11px; }
    </style>
</head>
<body>
<h1>Звіт Агента</h1>
<h2>Акт по реалізації транспортних квитків № {{ $meta['act_no'] }}</h2>

<table class="grid2 mt-4 mb-4">
    <tr>
        <td>{{ $meta['act_city'] }}</td>
        <td class="right">{{ $meta['act_date_human'] }}</td>
    </tr>
</table>

<p class="mb-2">
    Ми, що нижче підписалися, {{ $meta['agent_name'] }} з одного боку,
    та {{ $meta['carrier_name'] }} в особі Директора Жила М.В. з іншого боку,
    склали цей Звіт Агента про те, що у {{ $filters['from'] }}–{{ $filters['to'] }}
    Агент відповідно до Договору № {{ $meta['contract_no'] }} від {{ $meta['contract_date'] }}
    було реалізовано транспортних квитків у звітному періоді на загальну суму:
</p>

<table class="tbl mt-4 mb-4">
    <thead>
    <tr class="b center">
        <th>Найменування</th>
        <th>Сума, {{ $filters['currency'] === 'UAH' ? 'грн.' : $filters['currency'] }}</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td>Загальна кількість проданих квитків</td>
        <td class="right">{{ $count_sold }} шт.</td>
    </tr>
    <tr>
        <td>Загальна сума реалізації транспортних квитків</td>
        <td class="right">{{ number_format($totals['soldTotal'],2,',',' ') }}</td>
    </tr>
    <tr>
        <td>Загальна сума повернених транспортних квитків</td>
        <td class="right">{{ number_format($totals['returnedTotal'],2,',',' ') }}</td>
    </tr>
    <tr>
        <td>Сума агентської винагороди Агента</td>
        <td class="right">{{ number_format($totals['agentReward'],2,',',' ') }}</td>
    </tr>
    <tr>
        <td>Сума, що підлягає перерахуванню представнику Перевізника</td>
        <td class="right">{{ number_format($totals['toCarrier'],2,',',' ') }}</td>
    </tr>
    </tbody>
</table>

<p class="mb-2">Сторони претензій одна до одної не мають.</p>
<p class="mb-4">Даний Звіт є підставою для проведення взаєморозрахунків.</p>

<table class="grid2 mt-6">
    <tr>
        <td class="b center">АГЕНТ</td>
        <td class="b center">ПЕРЕВІЗНИК</td>
    </tr>
    <tr>
        <td class="center">{{ $meta['agent_name'] }}</td>
        <td class="center">{{ $meta['carrier_name'] }}</td>
    </tr>
    <tr>
        <td class="small" style="padding-top:8px; white-space:pre-line;">
            {{ $meta['agent_req'] }}</td>
        <td class="small" style="padding-top:8px; white-space:pre-line;">
            {{ $meta['carrier_req'] }}</td>
    </tr>
    <tr>
        <td class="center" style="padding-top:24px;">
            ФОП<br><br>
            ___________________________<br>
            (Кобзар Ю.С.)<br><br>
            МП
        </td>
        <td class="center" style="padding-top:24px;">
            Директор<br><br>
            ___________________________<br>
            (Жила М.В.)<br><br>
            МП
        </td>
    </tr>
</table>
</body>
</html>
