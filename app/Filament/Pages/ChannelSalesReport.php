<?php

namespace App\Filament\Pages;

use App\Services\Reports\ChannelSalesReportService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Illuminate\Support\Carbon;
use Filament\Notifications\Notification;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ChannelSalesSummaryExport;
use App\Exports\BookingsRowsExport;

class ChannelSalesReport extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'Звіти';
    protected static ?string $navigationLabel = 'Звіт по каналам продажу';
    protected static ?string $title           = "Звіт по каналам продажу";
    protected static string $view = 'filament.pages.channel-sales-report';
    public ?int $detailAgentId = null;   // null => direct
    public string $detailTitle = '';
    public array $detailRows = [];

    public ?array $data = [
        'from' => null,
        'to' => null,
        'currency' => 'UAH',
        'mode' => 'both', // agents | direct | both
        'agent_id' => null,
        'use_payment_method_filter' => false,
        'payment_method' => null,
        'agent_percent_on_sales' => 35,
    ];

    public array $report = [];

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
                // Дати + валюта
                Forms\Components\DatePicker::make('from')
                    ->label('Період з')->required()->columnSpan(2),
                Forms\Components\DatePicker::make('to')
                    ->label('по')->required()->columnSpan(2),
                Forms\Components\Select::make('currency')
                    ->label('Валюта')
                    ->options(['UAH'=>'UAH','PLN'=>'PLN','EUR'=>'EUR'])
                    ->default('UAH')->columnSpan(2),

                // Режим
                Forms\Components\Radio::make('mode')
                    ->label('Режим')
                    ->options(['both'=>'Обидва','agents'=>'Агенти','direct'=>'Самостійні'])
                    ->inline()->default('both')->reactive()->columnSpan(6),

                // Деталізація по агенту (лише в режимі "Агенти")
                Forms\Components\Select::make('agent_id')
                    ->label('Агент (деталізація)')
                    ->options(fn() => \App\Models\User::query()
                        ->when(method_exists(\App\Models\User::class,'role'),
                            fn($q)=>$q->role(['admin','manager']),
                            fn($q)=>$q->whereIn('role',['admin','manager']))
                        ->orderBy('name')->pluck('name','id')->toArray())
                    ->searchable()
                    ->helperText('Опціонально: деталізація по одному агенту')
                    ->visible(fn(Forms\Get $get) => $get('mode') === 'agents')
                    ->columnSpan(4),

                Forms\Components\TextInput::make('agent_percent_on_sales')
                    ->label('Відсоток Агента, %')
                    ->numeric()->minValue(0)->maxValue(100)->step(0.1)->default(35)
                    ->visible(fn(Forms\Get $get) => $get('mode') === 'agents')
                    ->columnSpan(2),

                // Фільтр за способом оплати (одна пара полів)
                Forms\Components\Toggle::make('use_payment_method_filter')
                    ->label('Фільтр за способом оплати')
                    ->reactive()->columnSpan(3),

                Forms\Components\Select::make('payment_method')
                    ->label('Спосіб оплати')
                    ->options(fn() => \App\Models\Booking::query()
                        ->whereNotNull('payment_method')
                        ->distinct()->orderBy('payment_method')
                        ->pluck('payment_method','payment_method')->toArray())
                    ->searchable()
                    ->visible(fn(Forms\Get $get) => (bool)$get('use_payment_method_filter'))
                    ->columnSpan(3),
            ]),
        ])->statePath('data');
    }


    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')->label('Згенерувати')->action(fn()=>$this->makeReport())->color('primary'),
            Action::make('exportXlsx')->label('Експорт XLSX')->color('success')->action('exportXlsx')->visible(fn()=>!empty($this->report)),
            Action::make('exportCsv')->label('Експорт CSV')->color('gray')->action('exportCsv')->visible(fn()=>!empty($this->report)),
        ];
    }

    public function makeReport(): void
    {
        // Беремо стан форми, але гарантуємо дефолти на всі ключі
        $v = $this->form->getState() ?? [];

        $defaults = [
            'from' => $this->data['from'] ?? now()->startOfMonth()->toDateString(),
            'to' => $this->data['to'] ?? now()->endOfMonth()->toDateString(),
            'currency' => $this->data['currency'] ?? 'UAH',
            'mode' => $this->data['mode'] ?? 'both',
            'agent_id' => $this->data['agent_id'] ?? null,
            'use_payment_method_filter' => $this->data['use_payment_method_filter'] ?? false,
            'payment_method' => $this->data['payment_method'] ?? null,
        ];

        $v = array_replace($defaults, $v);

        $pm = !empty($v['use_payment_method_filter']) ? ($v['payment_method'] ?? null) : null;

        $srv = app(\App\Services\Reports\ChannelSalesReportService::class);

        $this->report = $srv->generate(
            \Illuminate\Support\Carbon::parse($v['from']),
            \Illuminate\Support\Carbon::parse($v['to']),
            [
                'currency' => $v['currency'],
                'mode' => $v['mode'],
                'agent_id' => $v['agent_id'] ?? null,
                'payment_method' => $pm,
            ]
        );
    }

    public function showAgentDetails(int $agentId): void
    {
        $v = $this->form->getState();
        $srv = app(\App\Services\Reports\ChannelSalesReportService::class);

        $rows = $srv->listAgentSoldTickets(
            Carbon::parse($v['from']),
            Carbon::parse($v['to']),
            [
                'currency' => $v['currency'] ?? 'UAH',
                'payment_method' => !empty($v['use_payment_method_filter']) ? ($v['payment_method'] ?? null) : null,
                'agent_id' => $agentId,
            ]
        );

        $this->detailAgentId = $agentId;
        $this->detailTitle = 'Деталі: агент '.(\App\Models\User::find($agentId)->name ?? ('#'.$agentId));
        $this->detailRows = $rows;

        $this->dispatch('open-modal', id: 'agent-bookings-modal');
    }

    public function showDirectDetails(): void
    {
        $v = $this->form->getState();
        $srv = app(\App\Services\Reports\ChannelSalesReportService::class);

        $rows = $srv->listBookings(
            Carbon::parse($v['from']),
            Carbon::parse($v['to']),
            [
                'currency' => $v['currency'] ?? 'UAH',
                'payment_method' => !empty($v['use_payment_method_filter']) ? ($v['payment_method'] ?? null) : null,
                'agent_id' => null,
            ]
        );

        $this->detailAgentId = null;
        $this->detailTitle = 'Деталі: Самостійні';
        $this->detailRows = $rows;

        $this->dispatch('open-modal', id: 'agent-bookings-modal');
    }

    public function exportXlsx()
    {
        $v = $this->form->getState();

        $from = \Illuminate\Support\Carbon::parse($v['from']);
        $to   = \Illuminate\Support\Carbon::parse($v['to']);
        $currency = $v['currency'] ?? 'UAH';
        $pm = !empty($v['use_payment_method_filter']) ? ($v['payment_method'] ?? null) : null;
        $pct = (float)($v['agent_percent_on_sales'] ?? 35);

        /** @var \App\Services\Reports\ChannelSalesReportService $srv */
        $srv = app(\App\Services\Reports\ChannelSalesReportService::class);

        $report = $srv->generate($from, $to, [
            'currency'       => $currency,
            'mode'           => $v['mode'] ?? 'both',
            'agent_id'       => $v['agent_id'] ?? null,
            'payment_method' => $pm,
        ]);

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\ChannelSalesMultiSheetExport(
                $report, $from, $to, $currency, $pm, $pct
            ),
            'channel-sales-'.now()->format('Ymd_His').'.xlsx'
        );
    }


    public function exportCsv()
    {
        $v = $this->form->getState();
        $srv = app(\App\Services\Reports\ChannelSalesReportService::class);
        $report = $srv->generate(Carbon::parse($v['from']), Carbon::parse($v['to']), [
            'currency' => $v['currency'] ?? 'UAH',
            'mode'     => $v['mode'] ?? 'both',
            'agent_id' => $v['agent_id'] ?? null,
            'payment_method' => !empty($v['use_payment_method_filter']) ? ($v['payment_method'] ?? null) : null,
        ]);

        return Excel::download(
            new ChannelSalesSummaryExport($report, 'Звіт по каналам'),
            'channel-sales-'.now()->format('Ymd_His').'.csv',
            \Maatwebsite\Excel\Excel::CSV
        );
    }

    public function exportAgentDetailsXlsx()
    {
        $title = $this->detailTitle !== '' ? $this->detailTitle : 'Details';
        // передаємо як є — експортер сам підхопить додаткові колонки
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BookingsRowsExport($this->detailRows, $title),
            'channel-sales-details-'.now()->format('Ymd_His').'.xlsx'
        );
    }

}
