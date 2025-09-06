<?php

namespace App\Filament\Pages;

use App\Services\Reports\AgentSalesReportService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Illuminate\Support\Carbon;

class AgentSalesReport extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Звіти';
    protected static string $view = 'filament.pages.agent-sales-report';
    protected static ?string $navigationLabel = 'Звіт з проданих квитків';

    public ?array $data = [
        'from' => null,
        'to' => null,
        'currency' => 'UAH',
        'agent_name' => 'ФОП Кобзар Ю.С.',
        'carrier_name' => 'ТОВ МАКС БУС',
        'contract_no' => '0105/2024-А',
        'contract_date' => '01.05.2024',
        'agent_percent_on_sales' => 35,
        'retention_total_percent' => 10,
        'agent_retention_percent' => 1,
        'use_payment_method_filter' => false,
        'payment_method' => null,
        'act_no'   => '00001',
        'act_city' => 'м. Черкаси',
        'act_date' => null, // якщо null — підставимо кінець періоду
        'agent_requisites' => "тел.: +380 (099) 221-10-99,\nІПН 3348116789,\nР/р UA173220010000026005340016044,\nу банку УНІВЕРСАЛ БАНК, МФО 322001,\n20740, Черкаська обл., с.Головятине, вул.Незалежності, буд.10",
        'carrier_requisites' => "Код за ЄДРПОУ 43049327,\nтел.: +38 (097) 221-10-99,\nР/р UA943052990000026004021602992,\nу банку АТ КБ \"ПРИВАТБАНК\", МФО 305299,\n20251, Черкаська обл., м.Ватутіне, вул. Ювілейна 7, кв.35",

    ];

    public array $report = [];

    public function mount(): void
    {
        $this->data['from'] ??= now()->startOfMonth()->toDateString();
        $this->data['to']   ??= now()->endOfMonth()->toDateString();
        $this->data['contract_date'] ??= now()->toDateString();
        $this->form->fill($this->data);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(12)->schema([
                Forms\Components\DatePicker::make('from')
                    ->label('Період з')->required()->columnSpan(2),
                Forms\Components\DatePicker::make('to')
                    ->label('по')->required()->columnSpan(2),
                Forms\Components\Select::make('currency')
                    ->label('Валюта')->options(['UAH'=>'UAH','PLN'=>'PLN','EUR'=>'EUR'])->default('UAH')->columnSpan(2),
                Forms\Components\TextInput::make('agent_name')->label('АГЕНТ')->columnSpan(3),
                Forms\Components\TextInput::make('carrier_name')->label('ПЕРЕВІЗНИК')->columnSpan(3),

                Forms\Components\TextInput::make('contract_no')->label('Договір №')->columnSpan(2),
                Forms\Components\Section::make('Акт')
                    ->schema([
                        Forms\Components\TextInput::make('act_no')->label('№ Акта')->maxLength(20)->columnSpan(2),
                        Forms\Components\TextInput::make('act_city')->label('Місто')->default('м. Черкаси')->columnSpan(3),
                        Forms\Components\DatePicker::make('act_date')->label('Дата Акта')->helperText('Якщо порожньо — візьмемо дату "по"')->columnSpan(3),

                        Forms\Components\Textarea::make('agent_requisites')->label('Реквізити Агента')->rows(5)->columnSpan(6),
                        Forms\Components\Textarea::make('carrier_requisites')->label('Реквізити Перевізника')->rows(5)->columnSpan(6),
                    ])
                    ->columns(12),
                Forms\Components\DatePicker::make('contract_date')->label('від')->columnSpan(2),

                Forms\Components\TextInput::make('agent_percent_on_sales')->label('% агента (продаж)')->numeric()->default(35)->columnSpan(2),
                Forms\Components\TextInput::make('retention_total_percent')->label('% утримання при поверненні')->numeric()->default(10)->columnSpan(3),
                Forms\Components\TextInput::make('agent_retention_percent')->label('% агента з ціни при поверненні')->numeric()->default(1)->columnSpan(3),
                Forms\Components\Toggle::make('use_payment_method_filter')
                    ->label('Фільтрувати за способом оплати')
                    ->reactive()
                    ->inline(false)
                    ->columnSpan(3),

                Forms\Components\Select::make('payment_method')
                    ->label('Спосіб оплати')
                    ->options(fn () => \App\Models\Booking::query()
                        ->whereNotNull('payment_method')
                        ->select('payment_method')
                        ->distinct()
                        ->orderBy('payment_method')
                        ->pluck('payment_method', 'payment_method')
                        ->toArray())
                    ->searchable()
                    ->disabled(fn (\Filament\Forms\Get $get) => ! $get('use_payment_method_filter'))
                    ->columnSpan(3),
                ]),
        ])->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label('Згенерувати')
                ->color('success')
                ->action(fn() => $this->makeReport()),
            Action::make('export_excel')
                ->label('Експорт Excel')
                ->visible(fn() => filled($this->report))
                ->url(fn() => route('reports.agent-sales.excel', ['payload' => base64_encode(json_encode($this->data))]))
                ->openUrlInNewTab(),
            Action::make('export_pdf')
                ->label('Завантажити PDF')
                ->visible(fn() => filled($this->report))
                ->url(fn() => route('reports.agent-sales.pdf', ['payload' => base64_encode(json_encode($this->data))]))
                ->openUrlInNewTab(),
            Action::make('act_pdf')
                ->label('Акт (PDF)')
                ->color('info')
                ->visible(fn () => filled($this->report))
                ->url(fn () => route('reports.agent-sales.act-pdf', [
                    'payload' => base64_encode(json_encode($this->data)),
                ]))
                ->openUrlInNewTab(),

            Action::make('act_excel')
                ->label('Акт (Excel)')
                ->color('info')
                ->visible(fn () => filled($this->report))
                ->url(fn () => route('reports.agent-sales.act-excel', [
                    'payload' => base64_encode(json_encode($this->data)),
                ]))
                ->openUrlInNewTab(),
        ];
    }

    public function makeReport(): void
    {
        $v = $this->form->getState();
        $pm = !empty($v['use_payment_method_filter']) ? ($v['payment_method'] ?? null) : null;

        $service = app(AgentSalesReportService::class);
        $this->report = $service->generate(
            Carbon::parse($v['from']),
            Carbon::parse($v['to']),
            [
                'currency' => $v['currency'],
                'agent_percent_on_sales' => (float)$v['agent_percent_on_sales'],
                'retention_total_percent' => (float)$v['retention_total_percent'],
                'agent_retention_percent' => (float)$v['agent_retention_percent'],
                'payment_method' => $pm,
            ]
        );

        // підхопимо службові поля для заголовків
        $this->report['meta'] = [
            'agent_name'   => $v['agent_name'],
            'carrier_name' => $v['carrier_name'],
            'contract_no'  => $v['contract_no'],
            'contract_date'=> \Carbon\Carbon::parse($v['contract_date'])->format('d.m.Y'),
        ];
    }
}
