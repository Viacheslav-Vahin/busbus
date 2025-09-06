<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class AgentActExcel implements FromArray, WithEvents
{
    public function __construct(private array $r) {}

    public function array(): array { return [[]]; }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $s = $e->sheet->getDelegate();
                $s->getParent()->getDefaultStyle()->getFont()->setName('Arial')->setSize(11);
                foreach (['A'=>12,'B'=>68,'C'=>28] as $c=>$w) { $s->getColumnDimension($c)->setWidth($w); }

                $cur = $this->r['filters']['currency'] ?? 'UAH';
                $moneyFmt = '# ##0.00 "'.($cur === 'UAH' ? 'грн.' : $cur).'"';
                $row = 1;

                $s->mergeCells("A{$row}:C{$row}"); $s->setCellValue("A{$row}", "Звіт Агента");
                $s->getStyle("A{$row}")->getFont()->setBold(true)->setSize(16);
                $s->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); $row++;

                $s->mergeCells("A{$row}:C{$row}");
                $s->setCellValue("A{$row}", 'Акт по реалізації транспортних квитків № '.$this->r['meta']['act_no']);
                $s->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); $row += 2;

                // місто / дата
                $s->setCellValue("A{$row}", $this->r['meta']['act_city']);
                $s->mergeCells("B{$row}:C{$row}");
                $s->setCellValue("B{$row}", $this->r['meta']['act_date_human']);
                $s->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); $row += 2;

                // Преамбула
                $text = "Ми, що нижче підписалися, {$this->r['meta']['agent_name']} з одного боку, та {$this->r['meta']['carrier_name']} в особі Директора Жила М.В. з іншого боку, склали цей Звіт Агента про те, що у {$this->r['filters']['from']}–{$this->r['filters']['to']} Агент відповідно до Договору № {$this->r['meta']['contract_no']} від {$this->r['meta']['contract_date']} було реалізовано транспортних квитків у звітному періоді на загальну суму:";
                $s->mergeCells("A{$row}:C{$row}"); $s->setCellValue("A{$row}", $text); $row += 2;

                // Таблиця
                $head = $row;
                $s->setCellValue("A{$row}", "Найменування");
                $s->mergeCells("A{$row}:B{$row}");
                $s->setCellValue("C{$row}", "Сума, ".($cur === 'UAH' ? 'грн.' : $cur));
                $s->getStyle("A{$row}:C{$row}")->getFont()->setBold(true);
                $s->getStyle("A{$row}:C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $s->getStyle("A{$row}:C{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EDEDED'); $row++;

                $s->mergeCells("A{$row}:B{$row}"); $s->setCellValue("A{$row}", "Загальна кількість проданих квитків");
                $s->setCellValue("C{$row}", $this->r['count_sold']." шт."); $row++;

                $s->mergeCells("A{$row}:B{$row}"); $s->setCellValue("A{$row}", "Загальна сума реалізації транспортних квитків");
                $s->setCellValue("C{$row}", $this->r['totals']['soldTotal']); $row++;

                $s->mergeCells("A{$row}:B{$row}"); $s->setCellValue("A{$row}", "Загальна сума повернених транспортних квитків");
                $s->setCellValue("C{$row}", $this->r['totals']['returnedTotal']); $row++;

                $s->mergeCells("A{$row}:B{$row}"); $s->setCellValue("A{$row}", "Сума агентської винагороди Агента");
                $s->setCellValue("C{$row}", $this->r['totals']['agentReward']); $row++;

                $s->mergeCells("A{$row}:B{$row}"); $s->setCellValue("A{$row}", "Сума, що підлягає перерахуванню представнику Перевізника");
                $s->setCellValue("C{$row}", $this->r['totals']['toCarrier']);
                $last = $row;

                // рамки + формат грошей
                $s->getStyle("A{$head}:C{$last}")->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM);
                $s->getStyle("A{$head}:C{$last}")->getBorders()->getInside()->setBorderStyle(Border::BORDER_THIN);
                $s->getStyle("C".($head+1).":C{$last}")->getNumberFormat()->setFormatCode($moneyFmt);
                $row += 2;

                $s->mergeCells("A{$row}:C{$row}"); $s->setCellValue("A{$row}", "Сторони претензій одна до одної не мають."); $row++;
                $s->mergeCells("A{$row}:C{$row}"); $s->setCellValue("A{$row}", "Даний Звіт є підставою для проведення взаєморозрахунків."); $row += 2;

                // Підписи + реквізити
                $s->setCellValue("A{$row}", "АГЕНТ"); $s->setCellValue("C{$row}", "ПЕРЕВІЗНИК");
                $s->getStyle("A{$row}:C{$row}")->getFont()->setBold(true);
                $s->getStyle("A{$row}:C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); $row++;

                $s->setCellValue("A{$row}", $this->r['meta']['agent_name']); $s->setCellValue("C{$row}", $this->r['meta']['carrier_name']); $row++;

                $s->setCellValue("A{$row}", $this->r['meta']['agent_req']); $s->setCellValue("C{$row}", $this->r['meta']['carrier_req']);
                $s->getStyle("A{$row}:C{$row}")->getAlignment()->setWrapText(true); $row += 3;

                $s->setCellValue("A{$row}", "ФОП"); $s->setCellValue("C{$row}", "Директор"); $row+=2;
                $s->setCellValue("A{$row}", "Кобзар Ю.С."); $s->setCellValue("C{$row}", "Жила М.В."); $row+=2;
                $s->setCellValue("A{$row}", "МП"); $s->setCellValue("C{$row}", "МП");

                // Параметри друку
                $page = $s->getPageSetup();
                $page->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
                $page->setFitToWidth(1); $page->setFitToHeight(0);
                $m = $s->getPageMargins(); $m->setTop(0.4); $m->setBottom(0.4); $m->setLeft(0.5); $m->setRight(0.5);
            },
        ];
    }
}
