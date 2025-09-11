<?php
// app/Services/Reports/AgentSalesReportService.php

namespace App\Services\Reports;

use App\Models\Booking;
use Carbon\Carbon;

class AgentSalesReportService
{
    public function generate(Carbon $from, Carbon $to, array $opts = []): array
    {
        $currency       = $opts['currency'] ?? 'UAH';
        $paymentMethod  = $opts['payment_method'] ?? null;

        $agentPercentOnSales     = (float)($opts['agent_percent_on_sales'] ?? 35);
        $retentionTotalPercent   = (float)($opts['retention_total_percent'] ?? 10);
        $agentRetentionPercent   = (float)($opts['agent_retention_percent'] ?? 1);
        $carrierRetentionPercent = max($retentionTotalPercent - $agentRetentionPercent, 0);

        $agentIds = array_values(array_filter(array_map('intval', $opts['agent_ids'] ?? [])));

        $dateFrom = $from->toDateString();
        $dateTo   = $to->toDateString();

        // ПРОДАНІ
        $soldQ = Booking::query()
            ->with(['trip.route','route'])
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->where('status', 'paid')
            ->where('currency_code', $currency);

        if ($paymentMethod) {
            $soldQ->where('payment_method', $paymentMethod);
        }
        // НОВЕ: якщо вибрали агентів — беремо лише їх продажі
        if (!empty($agentIds)) {
            $soldQ->whereIn('agent_id', $agentIds);
        }

        $sold = $soldQ->get();

        $soldRows = $sold->map(function (Booking $b) use ($agentPercentOnSales) {
            $price     = (float)$b->price;
            $agentFee  = round($price * $agentPercentOnSales / 100, 2);
            $toCarrier = round($price - $agentFee, 2);

            return [
                'ticket_no'  => $this->ticketNo($b),
                'passenger'  => $this->passengerName($b),
                'direction'  => $this->direction($b),
                'date'       => optional($b->date)->format('d.m.Y'),
                'agent_pct'  => $agentPercentOnSales,
                'price'      => $price,
                'agent_fee'  => $agentFee,
                'to_carrier' => $toCarrier,
            ];
        })->values();

        // СКАСОВАНІ/ПОВЕРНЕНІ
        $canceledQ = Booking::query()
            ->with(['trip.route','route'])
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->whereIn('status', ['cancelled','refunded'])
            ->where('currency_code', $currency);

        if ($paymentMethod) {
            $canceledQ->where('payment_method', $paymentMethod);
        }
        if (!empty($agentIds)) {
            $canceledQ->whereIn('agent_id', $agentIds);
        }

        $canceled = $canceledQ->get();

        $canceledRows = $canceled->map(function (Booking $b) use ($agentRetentionPercent, $carrierRetentionPercent) {
            $price  = (float)$b->price;
            $agentFromRetention   = round($price * $agentRetentionPercent / 100, 2);
            $carrierFromRetention = round($price * $carrierRetentionPercent / 100, 2);

            return [
                'ticket_no'  => $this->ticketNo($b),
                'passenger'  => $this->passengerName($b),
                'direction'  => $this->direction($b),
                'date'       => optional($b->date)->format('d.m.Y'),
                'retention_pct' => $agentRetentionPercent + $carrierRetentionPercent,
                'price'      => $price,
                'agent_from_retention'   => $agentFromRetention,
                'carrier_from_retention' => $carrierFromRetention,
            ];
        })->values();

        // Підрахунки
        $soldTotal     = round($soldRows->sum('price'), 2);
        $returnedTotal = round($canceledRows->sum('price'), 2);
        $retainedTotal = round($canceledRows->sum(fn($r)=>$r['agent_from_retention']+$r['carrier_from_retention']), 2);

        $subtotal    = round($soldTotal - $returnedTotal, 2);
        $agentReward = round($soldRows->sum('agent_fee'), 2);
        $toCarrier   = round($subtotal - $agentReward, 2);

        return [
            'filters' => [
                'from' => $from->format('d.m.Y'),
                'to'   => $to->format('d.m.Y'),
                'currency' => $currency,
                'payment_method' => $paymentMethod,
                'agent_percent_on_sales'   => $agentPercentOnSales,
                'retention_total_percent'  => $retentionTotalPercent,
                'agent_retention_percent'  => $agentRetentionPercent,
                // НОВЕ: повернемо на випадок відображення/експорту
                'agent_ids' => $agentIds,
            ],
            'totals'   => compact('soldTotal','returnedTotal','retainedTotal','subtotal','agentReward','toCarrier'),
            'sold'     => $soldRows,
            'canceled' => $canceledRows,
        ];
    }

    private function ticketNo(Booking $b): string
    {
        return $b->ticket_serial
            ?: ($b->ticket_uuid ?: (string) $b->id);
    }

    private function passengerName(Booking $b): string
    {
        $p = $b->passengers;

        if (is_string($p)) {
            $decoded = json_decode($p, true);
            $p = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        // Підтримуємо формати:
        // [{first_name,last_name}], {first_name,last_name}, {name}, {full_name}, ...
        $first = null;
        if (is_array($p)) {
            $first = array_is_list($p) ? ($p[0] ?? null) : $p;
        }

        $firstName = trim((string)($first['first_name'] ?? $first['firstname'] ?? ''));
        $lastName  = trim((string)($first['last_name'] ?? $first['surname'] ?? ''));
        $full      = trim((string)($first['full_name'] ?? $first['name'] ?? ''));

        $candidate = trim($lastName . ' ' . $firstName);
        return $candidate !== '' ? $candidate : ($full !== '' ? $full : '');
    }

    private function direction(Booking $b): string
    {
        $route = $b->trip?->route ?: $b->route;
        if ($route && isset($route->start_point, $route->end_point)) {
            return $route->start_point . '-' . $route->end_point;
        }

        // fallback
        return 'Маршрут #' . ($b->route_id ?: ($b->trip?->route_id ?? '—'));
    }
}
