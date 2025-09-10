<?php

namespace App\Services\Reports;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Support\Str;

class BorderFlowReportService
{
    public function generate(Carbon $from, Carbon $to, array $opts = []): array
    {
        $dateFrom      = $from->toDateString();
        $dateTo        = $to->toDateString();
        $currency      = $opts['currency'] ?? null;              // null -> всі валюти
        $paymentMethod = $opts['payment_method'] ?? null;        // null -> будь-який
        $includeCancelled = (bool)($opts['include_cancelled'] ?? false); // якщо true — рахуємо paid+cancelled+refunded

        $q = Booking::query()
            ->with(['trip.route','route'])
            ->whereBetween('date', [$dateFrom, $dateTo]);

        if (!$includeCancelled) {
            $q->where('status', 'paid');
        } else {
            $q->whereIn('status', ['paid','cancelled','refunded']);
        }

        if ($currency) {
            $q->where('currency_code', $currency);
        }

        if ($paymentMethod) {
            $q->where('payment_method', $paymentMethod);
        }

        $all = $q->get();

        // Класифікація бронювання -> exit/entry/other
        $rows = $all->map(function($b){
            [$fromPoint, $toPoint] = $this->points($b);
            $fromUA = $this->isUa($fromPoint);
            $toUA   = $this->isUa($toPoint);

            $flow = 'other';
            if ($fromUA && !$toUA) $flow = 'exit';
            elseif (!$fromUA && $toUA) $flow = 'entry';
            // (внутрішні/зовнішні рейси не враховуємо в підсумках, але можемо показати в деталях за бажанням)

            return [
                'flow'          => $flow,
                'date'          => optional($b->date)->format('Y-m-d'),
                'ticket_no'     => $this->ticketNo($b),
                'passenger'     => $this->passengerName($b),
                'direction'     => $fromPoint.'-'.$toPoint,
                'price'         => (float)$b->price,
                'status'        => $b->status,
                'payment_method'=> $b->payment_method,
            ];
        });

        $exit = $rows->where('flow','exit');
        $entry= $rows->where('flow','entry');

        $summary = [
            'exit'  => [
                'count' => $exit->count(),
                'sum'   => round($exit->sum('price'), 2),
                'daily' => $exit->groupBy('date')->map(fn($g)=>[
                    'count'=>$g->count(), 'sum'=>round($g->sum('price'),2)
                ])->sortKeys()->all(),
            ],
            'entry' => [
                'count' => $entry->count(),
                'sum'   => round($entry->sum('price'), 2),
                'daily' => $entry->groupBy('date')->map(fn($g)=>[
                    'count'=>$g->count(), 'sum'=>round($g->sum('price'),2)
                ])->sortKeys()->all(),
            ],
        ];

        return [
            'filters' => [
                'from' => $from->format('d.m.Y'),
                'to'   => $to->format('d.m.Y'),
                'currency' => $currency,
                'payment_method' => $paymentMethod,
                'include_cancelled' => $includeCancelled,
            ],
            'summary' => $summary,
            'exit_rows'  => $exit->values()->all(),
            'entry_rows' => $entry->values()->all(),
        ];
    }

    /* ----------------- helpers ----------------- */

    private function points($b): array
    {
        $route = $b->trip?->route ?: $b->route;
        $from  = $route->start_point ?? '—';
        $to    = $route->end_point ?? '—';
        return [trim($from), trim($to)];
    }

    private function isUa(string $point): bool
    {
        $hay = mb_strtolower($point);
        foreach (config('reports.ua_markers', []) as $m) {
            if (Str::contains($hay, mb_strtolower($m))) return true;
        }
        return false;
    }

    private function ticketNo($b): string
    {
        return $b->ticket_serial ?: ($b->ticket_uuid ?: (string)$b->id);
    }

    private function passengerName($b): string
    {
        $p = $b->passengers;
        if (is_string($p)) {
            $dec = json_decode($p, true);
            $p = json_last_error() === JSON_ERROR_NONE ? $dec : null;
        }
        $f = is_array($p) ? (array_is_list($p) ? ($p[0] ?? []) : $p) : [];
        $fn = trim((string)($f['first_name'] ?? $f['firstname'] ?? ''));
        $ln = trim((string)($f['last_name']  ?? $f['surname']   ?? ''));
        $full = trim((string)($f['full_name'] ?? $f['name'] ?? ''));
        $cand = trim("$ln $fn");
        return $cand !== '' ? $cand : ($full !== '' ? $full : '');
    }
}
