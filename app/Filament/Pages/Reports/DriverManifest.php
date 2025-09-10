<?php

namespace App\Filament\Pages\Reports;

use App\Models\{Bus, Booking, Currency, AdditionalService};
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

use Filament\Forms\Components\Grid;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Actions as FormActions;
use Filament\Forms\Components\Actions\Action as FormAction;

class DriverManifest extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationGroup = 'Звіти';
    protected static ?string $navigationIcon  = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Маніфест водія';
    protected static ?string $title           = "Маніфест водія";

    protected static string $view             = 'filament.pages.reports.driver-manifest';

    public ?string $date   = null;
    public ?int    $bus_id = null;

    protected function getFormSchema(): array
    {
        return [
            Grid::make(12)
                ->schema([
                    DatePicker::make('date')
                        ->label('Дата рейсу')
                        ->native(false)
                        ->displayFormat('Y-m-d')
                        ->required()
                        ->columnSpan(3),

                    Select::make('bus_id')
                        ->label('Автобус')
                        ->options(\App\Models\Bus::pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->columnSpan(7),

                    // Кнопка в тій же лінійці
//                    FormActions::make([
//                        FormAction::make('generate')
//                            ->label('PDF')
//                            ->icon('heroicon-o-document-arrow-down')
//                            ->color('warning')
//                            ->submit('generate'),
//                    ])
//                        ->columnSpan(2)
//                        ->alignment('end'),
                ])
                ->columns(12)
                ->extraAttributes(['class' => 'driver-manifest-compact']),
        ];
    }

    public function generate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->validate([
            'date'   => 'required|date',
            'bus_id' => 'required|exists:buses,id',
        ]);

        $bus = Bus::findOrFail($this->bus_id);

        $bookings = Booking::query()
            ->with('route')
            ->where('bus_id', $bus->id)
            ->whereDate('date', $this->date)
            ->orderBy('seat_number')
            ->get();

        // 1) Курси (foreign per 1 UAH), як на фронті
        $fxMap = Currency::query()->pluck('rate', 'code')->map(fn($r) => (float) $r)->all();

        // 2) Мапа назв дод. послуг за id (якщо є така таблиця)
        $addSvcNames = class_exists(AdditionalService::class)
            ? AdditionalService::query()->pluck('name', 'id')->map(fn($v)=>trim((string)$v))->all()
            : [];

        $EXTRA_LABELS = ['coffee'=>'Кава','blanket'=>'Плед','service'=>'Сервіс'];

        $rows = $bookings->map(function (Booking $b) use ($fxMap, $EXTRA_LABELS, $addSvcNames) {
            $p0 = collect($b->passengers ?? [])->first();

            // ---- Ім'я/телефон
            $name = trim(
                ($p0['last_name'] ?? $b->surname ?? '') . ' ' .
                ($p0['first_name'] ?? $b->name ?? '')
            ) ?: ($b->passengerNames ?? '');
            $phone = $b->phone ?? ($p0['phone_number'] ?? $p0['phone'] ?? $b->passengerPhone ?? null);

            // ---- Екстри з двох джерел
            $extrasNames = [];

            // a) passengers[0].extras -> coffee/blanket/service
            if (!empty($p0['extras']) && is_array($p0['extras'])) {
                foreach ($p0['extras'] as $k) {
                    $label = $EXTRA_LABELS[$k] ?? null;
                    if ($label) $extrasNames[] = $label;
                }
            }

            // b) additional_services -> ["1","2"] або { ids: ["1","2"], meta: {...} }
            $as = $b->additional_services;
            if (is_string($as)) $as = json_decode($as, true);
            if (is_array($as)) {
                $ids = $as['ids'] ?? (is_array($as) && array_is_list($as) ? $as : []);
                $ids = array_filter(array_map('intval', $ids));
                if ($ids && $addSvcNames) {
                    foreach ($ids as $id) {
                        if (!empty($addSvcNames[$id])) $extrasNames[] = $addSvcNames[$id];
                    }
                }
            }

            $extrasLabel = implode(', ', array_values(array_unique(array_filter($extrasNames))));

            // ---- Коментарі/додатковий телефон з meta та note
            $notes = [];
            if (isset($as['meta']) && is_array($as['meta'])) {
                if (!empty($as['meta']['comment']))   $notes[] = trim($as['meta']['comment']);
                if (!empty($as['meta']['phone_alt'])) $notes[] = '+'.ltrim((string)$as['meta']['phone_alt'], '+');
            }
            if (!empty($p0['note'])) $notes[] = trim((string)$p0['note']);
            $note = trim(implode(' • ', array_unique(array_filter($notes))));

            // ---- Сума в UAH (правильний перерахунок)
            $currency = $b->currency_code ?: 'UAH';
            $priceUah = (float) ($b->price_uah ?? 0);
            if ($priceUah <= 0) {
                if ($currency === 'UAH') {
                    $priceUah = (float) ($b->price ?? 0);
                } else {
                    // fx_rate і rate у нас у форматі: foreign per 1 UAH
                    $fx = (float) ($b->fx_rate ?: ($fxMap[$currency] ?? 0));
                    if ($fx > 0) {
                        // foreign -> UAH
                        $priceUah = round(((float) $b->price) / $fx, 2);
                    } else {
                        $priceUah = 0.0; // немає курсу — краще 0, ніж помилкове число
                    }
                }
            }

            return [
                'id'         => $b->id,
                'seat'       => $b->seat_number,
                'name'       => $name,
                'phone'      => $phone,
                'paid'       => (bool) $b->paid_at,
                'paidLabel'  => $b->paid_at ? 'Оплачено' : 'На борту',
                'price_uah'  => $priceUah,
                'price_fx'   => (float) ($b->price ?? 0),
                'currency'   => $currency,
                'boarded_at' => $b->checked_in_at ? $b->checked_in_at->format('H:i') : null,
                'extras'     => $extrasLabel,
                'note'       => $note,
                'uuid'       => $b->ticket_uuid,
            ];
        });

        $totals = [
            'count'      => $rows->count(),
            'paid_uah'   => (float) $rows->where('paid', true)->sum('price_uah'),
            'unpaid_uah' => (float) $rows->where('paid', false)->sum('price_uah'),
        ];

        $first = $bookings->first();
        $routeTitle = $first?->route?->start_point.' — '.$first?->route?->end_point;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.driver-manifest', [
            'rows'   => $rows,
            'date'   => $this->date,
            'bus'    => $bus,
            'route'  => $routeTitle,
            'totals' => $totals,
        ])->setPaper('a4', 'portrait');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'manifest_'.$this->date.'_bus'.$bus->id.'.pdf'
        );
    }}
