<?php

namespace App\Services\Reports;

use App\Models\Booking;
use Carbon\Carbon;

class ChannelSalesReportService
{
    public function generate(Carbon $from, Carbon $to, array $opts = []): array
    {
        $currency = $opts['currency'] ?? 'UAH';
        $paymentMethod = $opts['payment_method'] ?? null;
        $mode = $opts['mode'] ?? 'both';      // 'agents' | 'direct' | 'both'
        $agentId = $opts['agent_id'] ?? null; // деталізація по одному агенту

        $dateFrom = $from->toDateString();
        $dateTo   = $to->toDateString();

        // Базові конструктори
        $baseSold = Booking::query()
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->where('status', 'paid')
            ->where('currency_code', $currency);

        $baseRet = Booking::query()
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->whereIn('status', ['cancelled','refunded'])
            ->where('currency_code', $currency);

        if ($paymentMethod) {
            $baseSold->where('payment_method', $paymentMethod);
            $baseRet->where('payment_method', $paymentMethod);
        }

        // AGENTS
        $agents = null;
        if ($mode !== 'direct') {
            $soldA = (clone $baseSold)->whereNotNull('agent_id');
            $retA  = (clone $baseRet)->whereNotNull('agent_id');

            if ($agentId) {
                $soldA->where('agent_id', $agentId);
                $retA ->where('agent_id', $agentId);
            }

            $soldAgg = $soldA->selectRaw('agent_id, COUNT(*) as cnt, SUM(price) as soldTotal')
                ->groupBy('agent_id')->get()->keyBy('agent_id');
            $retAgg  = $retA ->selectRaw('agent_id, SUM(price) as returnedTotal')
                ->groupBy('agent_id')->get()->keyBy('agent_id');

            $agentIds = $soldAgg->keys()->merge($retAgg->keys())->unique()->all();

            $agents = collect($agentIds)->map(function($id) use ($soldAgg, $retAgg) {
                $a = $soldAgg[$id] ?? (object)['cnt'=>0,'soldTotal'=>0];
                $r = $retAgg[$id]  ?? (object)['returnedTotal'=>0];
                return [
                    'agent_id'      => $id,
                    'agent_name'    => optional(\App\Models\User::find($id))->name ?? '—',
                    'count_sold'    => (int)$a->cnt,
                    'soldTotal'     => (float)$a->soldTotal,
                    'returnedTotal' => (float)$r->returnedTotal,
                    'net'           => round(($a->soldTotal ?? 0) - ($r->returnedTotal ?? 0), 2),
                ];
            })->sortByDesc('soldTotal')->values()->all();
        }

        // DIRECT
        $direct = null;
        if ($mode !== 'agents') {
            $soldD = (clone $baseSold)->whereNull('agent_id')->selectRaw('COUNT(*) cnt, SUM(price) soldTotal')->first();
            $retD  = (clone $baseRet )->whereNull('agent_id')->selectRaw('SUM(price) returnedTotal')->first();

            $soldCnt = (int)($soldD->cnt ?? 0);
            $soldSum = (float)($soldD->soldTotal ?? 0);
            $retSum  = (float)($retD->returnedTotal ?? 0);

            $direct = [
                'count_sold'    => $soldCnt,
                'soldTotal'     => $soldSum,
                'returnedTotal' => $retSum,
                'net'           => round($soldSum - $retSum, 2),
            ];
        }

        return [
            'filters' => [
                'from' => $from->format('d.m.Y'),
                'to'   => $to->format('d.m.Y'),
                'currency' => $currency,
                'mode'     => $mode,
                'agent_id' => $agentId,
                'payment_method' => $paymentMethod,
            ],
            'agents' => $agents,   // масив рядків або null
            'direct' => $direct,   // агрегований рядок або null
        ];
    }

    public function listBookings(Carbon $from, Carbon $to, array $opts = []): array
    {
        $currency       = $opts['currency'] ?? 'UAH';
        $paymentMethod  = $opts['payment_method'] ?? null;
        $agentId        = $opts['agent_id'] ?? null; // null => direct

        $q = \App\Models\Booking::query()
            ->with(['trip.route','route'])
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where('currency_code', $currency);

        if ($paymentMethod) {
            $q->where('payment_method', $paymentMethod);
        }

        if (is_null($agentId)) {
            $q->whereNull('agent_id');
        } else {
            $q->where('agent_id', $agentId);
        }

        // показуємо тільки фінальні: sold/refunded/cancelled
        $q->whereIn('status', ['paid','cancelled','refunded'])
            ->orderBy('date')->orderBy('id');

        return $q->get()->map(function($b){
            return [
                'ticket_no'      => $this->ticketNo($b),
                'passenger'      => $this->passengerName($b),
                'direction'      => $this->direction($b),
                'date'           => optional($b->date)->format('d.m.Y'),
                'price'          => (float)$b->price,
                'status'         => $b->status,
                'payment_method' => $b->payment_method,
            ];
        })->all();
    }

    // ... всередині класу

    public function listAgentSoldTickets(Carbon $from, Carbon $to, array $opts = []): array
    {
        $currency      = $opts['currency'] ?? 'UAH';
        $paymentMethod = $opts['payment_method'] ?? null;
        $agentId       = (int)($opts['agent_id'] ?? 0);
        $agentPct      = (float)($opts['agent_pct'] ?? 35);

        $q = \App\Models\Booking::query()
            ->with(['trip.route','route'])
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where('currency_code', $currency)
            ->where('status', 'paid')
            ->where('agent_id', $agentId)
            ->orderBy('date')->orderBy('id');

        if ($paymentMethod) {
            $q->where('payment_method', $paymentMethod);
        }

        return $q->get()->map(function($b) use ($agentPct) {
            $price     = (float)$b->price;
            $agentFee  = round($price * $agentPct / 100, 2);
            $toCarrier = round($price - $agentFee, 2);

            return [
                'ticket_no'   => $this->ticketNo($b),
                'passenger'   => $this->passengerName($b),
                'direction'   => $this->direction($b),
                'date'        => optional($b->date)->format('d.m.Y'),
                'agent_pct'   => $agentPct,
                'price'       => $price,
                'agent_fee'   => $agentFee,
                'to_carrier'  => $toCarrier,
            ];
        })->all();
    }

    private function ticketNo(\App\Models\Booking $b): string
    {
        return $b->ticket_serial ?: ($b->ticket_uuid ?: (string)$b->id);
    }

    private function passengerName(\App\Models\Booking $b): string
    {
        $p = $b->passengers;
        if (is_string($p)) {
            $decoded = json_decode($p, true);
            $p = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }
        $first = is_array($p) ? (array_is_list($p) ? ($p[0] ?? []) : $p) : [];
        $firstName = trim((string)($first['first_name'] ?? $first['firstname'] ?? ''));
        $lastName  = trim((string)($first['last_name']  ?? $first['surname']   ?? ''));
        $full      = trim((string)($first['full_name']  ?? $first['name']      ?? ''));
        $cand = trim($lastName.' '.$firstName);
        return $cand !== '' ? $cand : ($full !== '' ? $full : '');
    }

    private function direction(\App\Models\Booking $b): string
    {
        $route = $b->trip?->route ?: $b->route;
        return ($route && isset($route->start_point, $route->end_point))
            ? $route->start_point.'-'.$route->end_point
            : 'Маршрут #'.($b->route_id ?: ($b->trip?->route_id ?? '—'));
    }
}
