<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class BookingsRowsExport implements FromArray, WithHeadings, WithTitle
{
    public function __construct(public array $rows, public string $sheetTitle='Details') {}

    public function array(): array
    {
        $withAgent = isset($this->rows[0]['agent_pct']);
        if ($withAgent) {
            $i = 0;
            return array_map(function($r) use (&$i){
                $i++;
                return [
                    $i,
                    $r['ticket_no'],
                    $r['passenger'],
                    $r['direction'],
                    $r['date'],
                    $r['agent_pct'],
                    $r['price'],
                    $r['agent_fee'],
                    $r['to_carrier'],
                ];
            }, $this->rows);
        }

        // direct/simple
        return array_map(fn($r)=>[
            $r['ticket_no'],
            $r['passenger'],
            $r['direction'],
            $r['date'],
            $r['price'],
            $r['status'] ?? '',
            $r['payment_method'] ?? '',
        ], $this->rows);
    }

    public function headings(): array
    {
        $withAgent = isset($this->rows[0]['agent_pct']);
        return $withAgent
            ? ['№','№ квитка','Пасажир','Напрямок','Дата відправлення','Відсоток Агента','Ціна','Винагорода АГЕНТА','До виплати ПЕРЕВІЗНИКУ']
            : ['№ квитка','Пасажир','Напрямок','Дата','Ціна','Статус','Спосіб оплати'];
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }
}
