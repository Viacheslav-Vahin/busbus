<?php

namespace App\Http\Controllers\Reports;

use App\Services\Reports\AgentSalesReportService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use PDF;
use Carbon\Carbon;

class AgentSalesExportController
{
    public function excel(Request $request)
    {
        $data = json_decode(base64_decode($request->string('payload')), true);
        $pm = (!empty($data['use_payment_method_filter'])) ? ($data['payment_method'] ?? null) : null;
        $agentIds = !empty($data['filter_by_agents']) ? array_values(array_filter(array_map('intval', $data['agent_ids'] ?? []))) : [];
        $service = app(\App\Services\Reports\AgentSalesReportService::class);
        $report = $service->generate(
            \Carbon\Carbon::parse($data['from']),
            \Carbon\Carbon::parse($data['to']),
            [
                'currency' => $data['currency'],
                'agent_percent_on_sales' => (float)$data['agent_percent_on_sales'],
                'retention_total_percent' => (float)$data['retention_total_percent'],
                'agent_retention_percent' => (float)$data['agent_retention_percent'],
                'payment_method' => $pm,
                'agent_ids' => $agentIds,
            ]
        );

        $report['meta'] = [
            'agent_name'   => $data['agent_name'],
            'carrier_name' => $data['carrier_name'],
            'contract_no'  => $data['contract_no'],
            'contract_date'=> \Carbon\Carbon::parse($data['contract_date'])->format('d.m.Y'),
        ];

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\AgentSalesExcel($report),
            'agent-sales.xlsx'
        );
    }


    public function pdf(Request $request)
    {
        $data = json_decode(base64_decode($request->string('payload')), true);
        $pm = (!empty($data['use_payment_method_filter'])) ? ($data['payment_method'] ?? null) : null;
        $agentIds = !empty($data['filter_by_agents']) ? array_values(array_filter(array_map('intval', $data['agent_ids'] ?? []))) : [];

        $service = app(AgentSalesReportService::class);
        $report = $service->generate(
            Carbon::parse($data['from']),
            Carbon::parse($data['to']),
            [
                'currency' => $data['currency'],
                'agent_percent_on_sales' => (float)$data['agent_percent_on_sales'],
                'retention_total_percent' => (float)$data['retention_total_percent'],
                'agent_retention_percent' => (float)$data['agent_retention_percent'],
                'payment_method' => $pm,
                'agent_ids' => $agentIds,
            ]
        );

        $report['meta'] = [
            'agent_name'   => $data['agent_name'],
            'carrier_name' => $data['carrier_name'],
            'contract_no'  => $data['contract_no'],
            'contract_date'=> \Carbon\Carbon::parse($data['contract_date'])->format('d.m.Y'),
        ];

        $pdf = PDF::loadView('reports.agent-sales-export', $report)->setPaper('a4', 'portrait');
        return $pdf->download('agent-sales.pdf');
    }

    public function actPdf(\Illuminate\Http\Request $request)
    {
        $data = json_decode(base64_decode($request->string('payload')), true);

        // звіт
        $service = app(\App\Services\Reports\AgentSalesReportService::class);
        $report  = $service->generate(
            \Carbon\Carbon::parse($data['from']),
            \Carbon\Carbon::parse($data['to']),
            [
                'currency' => $data['currency'],
                'agent_percent_on_sales' => (float)$data['agent_percent_on_sales'],
                'retention_total_percent' => (float)$data['retention_total_percent'],
                'agent_retention_percent' => (float)$data['agent_retention_percent'],
                'payment_method' => !empty($data['use_payment_method_filter']) ? ($data['payment_method'] ?? null) : null,
            ]
        );

        // мета + реквізити
        $meta = [
            'agent_name'   => $data['agent_name'],
            'carrier_name' => $data['carrier_name'],
            'contract_no'  => $data['contract_no'],
            'contract_date'=> \Carbon\Carbon::parse($data['contract_date'])->format('d.m.Y'),
            'act_no'       => (string)($data['act_no'] ?? '00001'),
            'act_city'     => (string)($data['act_city'] ?? 'м. Черкаси'),
            'agent_req'    => (string)($data['agent_requisites'] ?? ''),
            'carrier_req'  => (string)($data['carrier_requisites'] ?? ''),
        ];

        // дата акта = data['act_date'] або "to"
        $actDate = !empty($data['act_date']) ? \Carbon\Carbon::parse($data['act_date']) : \Carbon\Carbon::parse($data['to']);
        $meta['act_date_human'] = self::formatUADateQuoted($actDate);

        $pdf = \PDF::loadView('reports.agent-sales-act', [
            'filters' => $report['filters'],
            'totals'  => $report['totals'],
            'count_sold' => count($report['sold']),
            'meta'    => $meta,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('akt-'.$meta['act_no'].'.pdf');
    }

    public function actExcel(\Illuminate\Http\Request $request)
    {
        $data = json_decode(base64_decode($request->string('payload')), true);

        $service = app(\App\Services\Reports\AgentSalesReportService::class);
        $report  = $service->generate(
            \Carbon\Carbon::parse($data['from']),
            \Carbon\Carbon::parse($data['to']),
            [
                'currency' => $data['currency'],
                'agent_percent_on_sales' => (float)$data['agent_percent_on_sales'],
                'retention_total_percent' => (float)$data['retention_total_percent'],
                'agent_retention_percent' => (float)$data['agent_retention_percent'],
                'payment_method' => !empty($data['use_payment_method_filter']) ? ($data['payment_method'] ?? null) : null,
            ]
        );

        $meta = [
            'agent_name'   => $data['agent_name'],
            'carrier_name' => $data['carrier_name'],
            'contract_no'  => $data['contract_no'],
            'contract_date'=> \Carbon\Carbon::parse($data['contract_date'])->format('d.m.Y'),
            'act_no'       => (string)($data['act_no'] ?? '00001'),
            'act_city'     => (string)($data['act_city'] ?? 'м. Черкаси'),
            'agent_req'    => (string)($data['agent_requisites'] ?? ''),
            'carrier_req'  => (string)($data['carrier_requisites'] ?? ''),
            'act_date_human' => self::formatUADateQuoted(!empty($data['act_date']) ? \Carbon\Carbon::parse($data['act_date']) : \Carbon\Carbon::parse($data['to'])),
        ];

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\AgentActExcel([
                'filters'    => $report['filters'],
                'totals'     => $report['totals'],
                'count_sold' => count($report['sold']),
                'meta'       => $meta,
            ]),
            'akt-'.$meta['act_no'].'.xlsx'
        );
    }

    /** “31” січня 2025 р. */
    private static function formatUADateQuoted(\Carbon\Carbon $d): string
    {
        $months = [1=>'січня','лютого','березня','квітня','травня','червня','липня','серпня','вересня','жовтня','листопада','грудня'];
        return '«'.$d->format('d').'» '.$months[(int)$d->format('n')].' '.$d->format('Y').' р.';
    }

}
