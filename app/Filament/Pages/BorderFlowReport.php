<?php

namespace App\Filament\Pages;

use App\Services\Reports\BorderFlowReportService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\BorderFlowMultiSheetExport;

class BorderFlowReport extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationGroup = 'Звіти';
    protected static ?string $navigationLabel = "В'їзд / Виїзд";
    protected static ?string $title           = "В'їзд / Виїзд";
    protected static string $view = 'filament.pages.border-flow-report';

    public ?array $data = [
        'from' => null,
        'to'   => null,
        'currency' => null, // null => всі
        'use_payment_method_filter' => false,
        'payment_method' => null,
        'include_cancelled' => false,
    ];

    public array $report = [];

    // дані модалки
    public string $detailType = ''; // 'exit'|'entry'
    public array $detailRows = [];

    public function mount(): void
    {
        $this->data['from'] ??= now()->startOfMonth()->toDateString();
        $this->data['to']   ??= now()->endOfMonth()->toDateString();
        $this->form->fill($this->data);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(12)->schema([
                Forms\Components\DatePicker::make('from')->label('Період з')->required()->columnSpan(3),
                Forms\Components\DatePicker::make('to')->label('по')->required()->columnSpan(3),

                Forms\Components\Select::make('currency')->label('Валюта')
                    ->options(['UAH'=>'UAH','PLN'=>'PLN','EUR'=>'EUR'])->nullable()
                    ->helperText('Порожнє — всі валюти')->columnSpan(2),

                Forms\Components\Toggle::make('include_cancelled')
                    ->label('Включати скасовані/повернені')->columnSpan(2),

                Forms\Components\Toggle::make('use_payment_method_filter')
                    ->label('Фільтр за способом оплати')->reactive()->columnSpan(2),

                Forms\Components\Select::make('payment_method')->label('Спосіб оплати')
                    ->options(fn() => \App\Models\Booking::query()
                        ->whereNotNull('payment_method')->distinct()->orderBy('payment_method')
                        ->pluck('payment_method','payment_method')->toArray())
                    ->searchable()
                    ->visible(fn(Forms\Get $get) => (bool)$get('use_payment_method_filter'))
                    ->columnSpan(4),
            ]),
        ])->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')->label('Згенерувати')
                ->color('primary')->action(fn()=> $this->makeReport()),
            Action::make('exportXlsx')->label('Експорт XLSX')
                ->color('success')->visible(fn()=>!empty($this->report))
                ->action('exportXlsx'),
        ];
    }

    public function makeReport(): void
    {
        $v = $this->form->getState();
        $pm = !empty($v['use_payment_method_filter']) ? ($v['payment_method'] ?? null) : null;

        /** @var BorderFlowReportService $srv */
        $srv = app(BorderFlowReportService::class);

        $this->report = $srv->generate(
            Carbon::parse($v['from']),
            Carbon::parse($v['to']),
            [
                'currency' => $v['currency'] ?: null,
                'payment_method' => $pm,
                'include_cancelled' => (bool)($v['include_cancelled'] ?? false),
            ]
        );
    }

    public function showDetails(string $type): void
    {
        $this->detailType = $type; // 'exit'|'entry'
        $this->detailRows = $type === 'exit' ? ($this->report['exit_rows'] ?? [])
            : ($this->report['entry_rows'] ?? []);
        $this->dispatch('open-modal', id: 'border-flow-details');
    }

    public function exportXlsx()
    {
        $v = $this->form->getState();
        $pm = !empty($v['use_payment_method_filter']) ? ($v['payment_method'] ?? null) : null;

        return Excel::download(
            new BorderFlowMultiSheetExport(
                $this->report,
                Carbon::parse($v['from']),
                Carbon::parse($v['to']),
                $v['currency'] ?: null,
                $pm
            ),
            'border-flow-'.now()->format('Ymd_His').'.xlsx'
        );
    }
}
