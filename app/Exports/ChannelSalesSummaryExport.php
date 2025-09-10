<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ChannelSalesSummaryExport implements FromArray, WithHeadings, WithTitle
{
    public function __construct(public array $report, public string $title = 'Summary') {}

    public function array(): array
    {
        $rows = [];
        $rows[] = ['Агент','К-сть','Продано','Повернено','Нетто'];

        foreach (($this->report['agents'] ?? []) as $r) {
            $rows[] = [
                $r['agent_name'],
                $r['count_sold'],
                $r['soldTotal'],
                $r['returnedTotal'],
                $r['net'],
            ];
        }

        if (!is_null($this->report['direct'])) {
            $d = $this->report['direct'];
            $rows[] = []; // пустий рядок
            $rows[] = ['Самостійні', $d['count_sold'], $d['soldTotal'], $d['returnedTotal'], $d['net']];
        }

        return $rows;
    }

    public function headings(): array
    {
        return []; // уже повертаємо у array() першим рядком
    }

    public function title(): string
    {
        return $this->title;
    }
}
