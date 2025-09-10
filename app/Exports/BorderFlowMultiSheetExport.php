<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class BorderFlowMultiSheetExport implements WithMultipleSheets
{
    public function __construct(
        protected array $report,
        protected Carbon $from,
        protected Carbon $to,
        protected ?string $currency,
        protected ?string $paymentMethod,
    ) {}

    public function sheets(): array
    {
        $sheets = [];

        // Summary
        $summaryRows = [
            ['Напрям', 'К-сть', 'Сума'],
            ['Виїзд з України', $this->report['summary']['exit']['count'] ?? 0, $this->report['summary']['exit']['sum'] ?? 0],
            ['Вʼїзд в Україну', $this->report['summary']['entry']['count'] ?? 0, $this->report['summary']['entry']['sum'] ?? 0],
        ];
        $sheets[] = new ArraySheetExport($summaryRows, 'Зведення');

        // Деталі
        $sheets[] = new BookingsRowsExport($this->report['exit_rows'] ?? [],  'Виїзд з України');
        $sheets[] = new BookingsRowsExport($this->report['entry_rows'] ?? [], 'Вʼїзд в Україну');

        return $sheets;
    }
}
