<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class AgentSalesExport implements FromView
{
    public function __construct(private array $report) {}

    public function view(): View
    {
        return view('reports.agent-sales-export', $this->report);
    }
}
