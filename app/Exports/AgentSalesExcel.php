<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class AgentSalesExcel implements FromArray, WithEvents
{
    public function __construct(private array $r) {}

    public function array(): array { return [[]]; }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $e) {
                $s = $e->sheet->getDelegate();
                $s->getParent()->getDefaultStyle()->getFont()->setName('Arial')->setSize(11);

                // Ширини колонок (A ширша, щоб не було ### у "Продано")
                foreach (['A'=>18,'B'=>16,'C'=>32,'D'=>36,'E'=>18,'F'=>16,'G'=>16,'H'=>20,'I'=>24] as $c=>$w) {
                    $s->getColumnDimension($c)->setWidth($w);
                }

                $cur = $this->r['filters']['currency'] ?? 'UAH';
                $moneyFmt = '# ##0.00 "'.$cur.'"';

                $row = 1;

                // ===== Заголовок
                $s->mergeCells("A{$row}:I{$row}");
                $s->setCellValue("A{$row}", 'Звіт з проданих квитків за період '.$this->r['filters']['from'].'-'.$this->r['filters']['to']);
                $s->getStyle("A{$row}")->getFont()->setBold(true)->setSize(16);
                $s->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); $row++;

                $s->mergeCells("A{$row}:I{$row}");
                $s->setCellValue("A{$row}", 'Агентський договір №'.$this->r['meta']['contract_no'].' від '.$this->r['meta']['contract_date']);
                $s->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); $row++;

                $s->mergeCells("A{$row}:I{$row}");
                $s->setCellValue("A{$row}", 'АГЕНТ: '.$this->r['meta']['agent_name']);
                $s->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); $row++;

                $s->mergeCells("A{$row}:I{$row}");
                $s->setCellValue("A{$row}", 'ПЕРЕВІЗНИК: '.$this->r['meta']['carrier_name']);
                $s->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $row += 2;

                // ===== Підсумки (дворядкова шапка)
                $s->mergeCells("A{$row}:I{$row}");
                $s->setCellValue("A{$row}", 'Всього по квитках:');
                $s->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
                $s->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $row++;

                $h1 = $row; $h2 = $row + 1;
                $s->mergeCells("A{$h1}:A{$h2}");  $s->setCellValue("A{$h1}", 'Продано');
                $s->mergeCells("B{$h1}:C{$h1}");  $s->setCellValue("B{$h1}", 'Повернено');
                $s->mergeCells("D{$h1}:D{$h2}");  $s->setCellValue("D{$h1}", 'Підсумкова сума');
                $s->mergeCells("E{$h1}:E{$h2}");  $s->setCellValue("E{$h1}", 'Винагорода АГЕНТА');
                $s->mergeCells("F{$h1}:F{$h2}");  $s->setCellValue("F{$h1}", 'До виплати ПЕРЕВІЗНИКУ');

                $s->setCellValue("B{$h2}", 'Разом');
                $s->setCellValue("C{$h2}", 'Утриманий при поверненні');

                $s->getStyle("A{$h1}:F{$h2}")->getFont()->setBold(true);
                $s->getStyle("A{$h1}:F{$h2}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);
                $s->getStyle("A{$h1}:F{$h2}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EDEDED');

                $d = $h2 + 1;
                $t = $this->r['totals'];
                $s->fromArray([[ $t['soldTotal'], $t['returnedTotal'], $t['retainedTotal'], $t['subtotal'], $t['agentReward'], $t['toCarrier'] ]], null, "A{$d}");
                $s->getStyle("A{$d}:F{$d}")->getNumberFormat()->setFormatCode($moneyFmt);
                $s->getStyle("D{$d}:F{$d}")->getFont()->setBold(true);

                $s->getStyle("A{$h1}:F{$d}")->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM);
                $s->getStyle("A{$h1}:F{$d}")->getBorders()->getInside()->setBorderStyle(Border::BORDER_THIN);

                $row = $d + 2;

                // ===== Продані квитки
                $s->mergeCells("A{$row}:I{$row}");
                $s->setCellValue("A{$row}", 'Продані квитки');
                $s->getStyle("A{$row}")->getFont()->setBold(true)->setSize(13);
                $row++;

                $s->setCellValue("A{$row}", 'У валюті: '.$cur);
                $s->mergeCells("A{$row}:I{$row}");
                $row++;

                $head = $row;
                $s->fromArray([[
                    '№','№ квитка','Пасажир','Напрямок','Дата відправлення',
                    'Відсоток Агента','Ціна','Винагорода АГЕНТА','До виплати ПЕРЕВІЗНИКУ'
                ]], null, "A{$row}");
                $s->getStyle("A{$row}:I{$row}")->getFont()->setBold(true);
                $s->getStyle("A{$row}:I{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
                $s->getRowDimension($row)->setRowHeight(24);
                $s->getStyle("A{$row}:I{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9D9D9');
                $row++;

                $i = 1;
                foreach ($this->r['sold'] as $r) {
                    $s->fromArray([[
                        $i++,
                        (string)$r['ticket_no'],
                        (string)$r['passenger'],
                        (string)$r['direction'],
                        $r['date'],
                        $r['agent_pct'],
                        (float)$r['price'],
                        (float)$r['agent_fee'],
                        (float)$r['to_carrier'],
                    ]], null, "A{$row}");
                    $row++;
                }
                $last = $row - 1;
                if ($last >= $head) {
                    $s->getStyle("A{$head}:I{$last}")->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM);
                    $s->getStyle("A{$head}:I{$last}")->getBorders()->getInside()->setBorderStyle(Border::BORDER_THIN);

                    $s->getStyle("A".($head+1).":A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $s->getStyle("E".($head+1).":F{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $s->getStyle("G".($head+1).":I{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    $s->getStyle("E".($head+1).":E{$last}")->getNumberFormat()->setFormatCode('dd.mm.yyyy');
                    $s->getStyle("F".($head+1).":F{$last}")->getNumberFormat()->setFormatCode('0');
                    $s->getStyle("G".($head+1).":I{$last}")->getNumberFormat()->setFormatCode($moneyFmt);

                    $s->freezePane("A".($head+1));
                }

                // ===== Скасовані квитки (відповідальність агента)
                $row += 1;
                $s->mergeCells("A{$row}:I{$row}");
                $s->setCellValue("A{$row}", 'Скасовані квитки в рамках договірної відповідальності АГЕНТА');
                $s->getStyle("A{$row}")->getFont()->setBold(true)->setSize(13);
                $row++;

                $cHead = $row;
                $s->fromArray([[
                    '№','№ квитка','Пасажир','Напрямок','Дата відправлення',
                    'Відсоток (утримання)','Ціна квитка','Винагорода АГЕНТА','До виплати ПЕРЕВІЗНИКУ'
                ]], null, "A{$row}");
                $s->getStyle("A{$row}:I{$row}")->getFont()->setBold(true);
                $s->getStyle("A{$row}:I{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
                $s->getRowDimension($row)->setRowHeight(24);
                $s->getStyle("A{$row}:I{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9D9D9');
                $row++;

                $k = 1;
                foreach ($this->r['canceled'] as $r) {
                    $s->fromArray([[
                        $k++,
                        (string)$r['ticket_no'],
                        (string)$r['passenger'],
                        (string)$r['direction'],
                        $r['date'],
                        $r['retention_pct'],
                        (float)$r['price'],
                        (float)$r['agent_from_retention'],
                        (float)$r['carrier_from_retention'],
                    ]], null, "A{$row}");
                    $row++;
                }
                $cLast = $row - 1;
                if ($cLast >= $cHead) {
                    $s->getStyle("A{$cHead}:I{$cLast}")->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM);
                    $s->getStyle("A{$cHead}:I{$cLast}")->getBorders()->getInside()->setBorderStyle(Border::BORDER_THIN);

                    $s->getStyle("A".($cHead+1).":A{$cLast}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $s->getStyle("E".($cHead+1).":F{$cLast}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $s->getStyle("G".($cHead+1).":I{$cLast}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    $s->getStyle("E".($cHead+1).":E{$cLast}")->getNumberFormat()->setFormatCode('dd.mm.yyyy');
                    $s->getStyle("F".($cHead+1).":F{$cLast}")->getNumberFormat()->setFormatCode('0');
                    $s->getStyle("G".($cHead+1).":I{$cLast}")->getNumberFormat()->setFormatCode($moneyFmt);
                }

                // ===== Підписи
                $row += 2;
                $s->setCellValue("A{$row}", 'АГЕНТ: '.$this->r['meta']['agent_name']);
                $s->mergeCells("A{$row}:D{$row}");
                $s->setCellValue("F{$row}", 'ПЕРЕВІЗНИК: '.$this->r['meta']['carrier_name']);
                $s->mergeCells("F{$row}:I{$row}");
                $row += 2;
                $s->setCellValue("A{$row}", 'Підпис ____________________   ПІБ _______________________');
                $s->mergeCells("A{$row}:D{$row}");
                $s->setCellValue("F{$row}", 'Підпис ____________________   ПІБ _______________________');
                $s->mergeCells("F{$row}:I{$row}");

                // Друк
                $page = $s->getPageSetup();
                $page->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
                $page->setFitToWidth(1);
                $page->setFitToHeight(0);
                $m = $s->getPageMargins();
                $m->setTop(0.4); $m->setBottom(0.4); $m->setLeft(0.4); $m->setRight(0.4);
            },
        ];
    }
}
