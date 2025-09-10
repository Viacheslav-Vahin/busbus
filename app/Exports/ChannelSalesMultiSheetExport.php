<?php

namespace App\Exports;

use App\Services\Reports\ChannelSalesReportService;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ChannelSalesMultiSheetExport implements WithMultipleSheets
{
    public function __construct(
        protected array  $report,           // результат ->generate()
        protected Carbon $from,
        protected Carbon $to,
        protected string $currency,
        protected ?string $paymentMethod,   // null якщо фільтр вимкнений
        protected float $agentPercent       // для розрахунку винагороди
    ) {}

    public function sheets(): array
    {
        $sheets = [];

        // 1) Summary
        $sheets[] = new ChannelSalesSummaryExport($this->report, 'Звіт по каналам');

        /** @var ChannelSalesReportService $srv */
        $srv = app(ChannelSalesReportService::class);

        // 2) Деталі по кожному агенту (як у модалці з %/винагородою/до виплати)
        foreach (($this->report['agents'] ?? []) as $row) {
            $agentId   = (int)$row['agent_id'];
            $agentName = (string)($row['agent_name'] ?? ('Агент #'.$agentId));

            $details = $srv->listAgentSoldTickets(
                $this->from,
                $this->to,
                [
                    'currency'       => $this->currency,
                    'payment_method' => $this->paymentMethod,
                    'agent_id'       => $agentId,
                    'agent_pct'      => $this->agentPercent,
                ]
            );

            $sheets[] = new BookingsRowsExport($details, 'Агент — '.$agentName);
        }

        // 3) Самостійні (direct) — базові колонки
        if (!is_null($this->report['direct'])) {
            $directRows = $srv->listBookings(
                $this->from,
                $this->to,
                [
                    'currency'       => $this->currency,
                    'payment_method' => $this->paymentMethod,
                    'agent_id'       => null, // direct
                ]
            );

            $sheets[] = new BookingsRowsExport($directRows, 'Самостійні');
        }

        return $sheets;
    }
}
