<?php

namespace App\Filament\Resources;

//use Livewire\Livewire;
use App\Filament\Resources\BookingResource\Pages;
use App\Models\Booking;
use App\Models\Bus;
use App\Models\Route;
use App\Models\Trip;
use App\Models\Stop;
use App\Models\BusStop;
use App\Models\Discount;
use App\Models\GlobalAccount;
use App\Models\AdditionalService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Filament\Forms\Set;
use Filament\Forms\Components\Livewire;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\ViewField;
use Illuminate\Support\HtmlString;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use App\Models\CompanyProfile;
use Illuminate\Support\Collection;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use App\Services\TicketService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Mail\TicketPdfMail;
use Filament\Notifications\Notification;
use App\Console\Commands\SendTripReminders;
use App\Models\NotificationLog;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\BookingsExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use App\Imports\PassengersImport;
use Filament\Forms\Components\FileUpload;

//use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;

//use Filament\Tables\Filters\Indicator;
use Filament\Forms\Get;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('user_id')
                    ->default(auth()->id())
                    ->hidden()
                    ->required(),

                Grid::make(['default' => 3])
                    ->schema([

                        Hidden::make('bus_id'), // виставлятимемо автоматично після вибору рейсу
                        Hidden::make('route_id'),
                        Hidden::make('destination_id'),
//                        Hidden::make('trip_id'),

                        // ЗВІДКИ (stop_id)
                        Select::make('start_stop_id')
                            ->label('Виїзд з (зупинка)')
                            ->options(function () {
                                // всі зупинки, які десь використовуються як boarding
                                $ids = BusStop::where('type', 'boarding')->pluck('stop_id')->unique();
                                return Stop::whereIn('id', $ids)->orderBy('name')->pluck('name', 'id')->toArray();
                            })
                            ->reactive()
                            ->required()
                            ->afterStateUpdated(function (callable $set) {
                                $set('end_stop_id', null);
                                $set('trip_id', null);
                                $set('bus_id', null);
                                $set('route_id', null);
                                $set('destination_id', null);
                                $set('seat_layout', '[]');
                                $set('selected_seat', null);
                                $set('seat_number', null);
                                $set('price', null);
                            }),

                        // КУДИ (stop_id)
                        Select::make('end_stop_id')
                            ->label('Прибуття до (зупинка)')
                            ->options(function (Get $get) {
                                $fromId = (int)$get('start_stop_id');
                                if (!$fromId) return [];
                                $busIds = BusStop::where('type', 'boarding')
                                    ->where('stop_id', $fromId)
                                    ->pluck('bus_id');

// ✅ залишаємо лише автобуси з графіком
                                $busIds = \App\Models\Bus::query()
                                    ->whereIn('id', $busIds)
//                                    ->hasAnySchedule()
                                    ->pluck('id');

                                $stopIds = BusStop::whereIn('bus_id', $busIds)
                                    ->where('type', 'dropping')
                                    ->pluck('stop_id')
                                    ->unique();
//                                // Знайти всі автобуси, які мають boarding на $fromId
//                                $busIds = BusStop::where('type', 'boarding')->where('stop_id', $fromId)->pluck('bus_id');
//
//                                // Для цих автобусів — усі dropping stops
//                                $stopIds = BusStop::whereIn('bus_id', $busIds)->where('type', 'dropping')->pluck('stop_id')->unique();

                                return Stop::whereIn('id', $stopIds)->orderBy('name')->pluck('name', 'id')->toArray();
                            })
                            ->reactive()
                            ->required()
                            ->afterStateUpdated(function (callable $get, callable $set) {
                                $set('trip_id', null);
                                $set('bus_id', null);
                                $set('route_id', null);
                                $set('destination_id', null);
                                $set('seat_layout', '[]');
                                $set('selected_seat', null);
                                $set('seat_number', null);
                                $set('price', null);

                                $set('available_dates', \App\Filament\Resources\BookingResource::getAvailableDatesByStops(
                                    (int)$get('start_stop_id'), (int)$get('end_stop_id')
                                ));
                            }),
                        Hidden::make('available_dates')
                            ->default([])
                            ->dehydrated(false)
                            ->reactive(),
                        // ВИБІР РЕЙСУ (trip)
                        Select::make('trip_id')
                            ->label('Виберіть рейс')
                            ->required()
                            ->searchable()
                            ->reactive()
                            ->live() // ⬅️ щоб відправлялося одразу, без onBlur
                            ->options(function (Get $get) {
                                $fromId = (int)$get('start_stop_id');
                                $toId = (int)$get('end_stop_id');
                                if (!$fromId || !$toId) return [];

                                $trips = Trip::with('bus', 'startStop', 'endStop')
                                    ->whereHas('bus', function ($q) use ($fromId, $toId) {
                                        $q->forStops($fromId, $toId);   // ✅
                                    })
                                    ->where(fn($q) => $q->where('start_stop_id', $fromId)->orWhereNull('start_stop_id'))
                                    ->where(fn($q) => $q->where('end_stop_id', $toId)->orWhereNull('end_stop_id'))
                                    ->orderBy('departure_time')
                                    ->get();

                                $opts = [];
                                foreach ($trips as $t) {
                                    $bus = $t->bus;
                                    $opts[$t->id] = sprintf('%s — %s → %s (відпр. %s, приб. %s)',
                                        $bus->name ?? ('#' . $t->bus_id),
                                        optional($t->startStop)->name ?? '—',
                                        optional($t->endStop)->name ?? '—',
                                        $t->departure_time ?: '—',
                                        $t->arrival_time ?: '—'
                                    );
                                }
                                asort($opts);
                                return $opts;
                            })
                            ->afterStateUpdated(function (callable $get, callable $set) {
                                $tripId = (int)$get('trip_id');

                                // почистити залежні поля відразу
                                $set('seat_layout', '[]');
                                $set('selected_seat', null);
                                $set('seat_number', null);
                                $set('seat_price', null);

                                // скинути список дат щоб календар не показував старе
                                $set('available_dates', []);

                                if ($tripId) {
                                    $trip = \App\Models\Trip::with('bus')->find($tripId);

                                    $set('bus_id', $trip?->bus_id);
                                    $set('route_id', $trip?->bus?->route_id);
                                    $set('destination_id', $trip?->bus?->route_id);
                                    $set('base_price', $trip?->calculatePrice() ?? 0);

                                    if ($trip?->bus_id) {
                                        $dates = self::getAvailableDatesForBus((int)$trip->bus_id);
                                        $set('available_dates', $dates);

                                        // якщо поточна дата не входить у новий список — скинемо її
                                        $current = $get('date');
                                        if ($current && !in_array($current, $dates, true)) {
                                            $set('date', null);
                                        } elseif ($current) {
                                            // дата ок — одразу підтягнемо схему місць для нового автобуса
                                            self::loadBusSeatLayout((int)$trip->bus_id, $current, $set);
                                        }
                                    }

                                    self::recomputePrice($get, $set);
                                } else {
                                    $set('date', null);
                                }
                            }),

                        DatePicker::make('date')
                            ->label('Дата поїздки')
                            ->required()
                            ->reactive()
//                            ->disabled(fn(Get $get) => blank($get('trip_id'))) // не відкривати без рейсу
                            ->extraAttributes(fn(Get $get) => [
                                // коли міняється trip_id — інпут отримує новий key => flatpickr створюється заново
                                'wire:key' => 'date-' . ($get('trip_id') ?? 'none'),
                                // блокувати, поки триває оновлення trip_id
                                'wire:loading.attr' => 'disabled',
                                'wire:target' => 'data.trip_id',
                            ])
                            ->afterStateUpdated(function (callable $get, callable $set) {
                                $busId = (int)$get('bus_id');
                                $date = $get('date');
                                if ($busId && $date) {
                                    self::loadBusSeatLayout($busId, $date, $set);
                                }
                            })
                            ->disabledDates(function (callable $get) {
                                // ⬅️ тепер DatePicker «бачить» available_dates як залежність
                                $available = (array)($get('available_dates') ?? []);
                                if (!$available) return [];

                                $today = \Carbon\Carbon::today();
                                $nextYear = $today->copy()->addYear();
                                $disabled = [];

                                while ($today->lte($nextYear)) {
                                    $d = $today->format('Y-m-d');
                                    if (!in_array($d, $available, true)) $disabled[] = $d;
                                    $today->addDay();
                                }
                                return $disabled;
                            })
                            ->minDate(now())
                            ->closeOnDateSelection(true)
                            ->native(false),
                    ]),

                Grid::make([
                    'default' => 1,
                ])
                    ->schema([
                        Repeater::make('passengers')
                            ->label('Дані пасажирів')
                            ->schema([
                                TextInput::make('name')
                                    ->label("Ім'я пасажира")
                                    ->required(),
                                TextInput::make('surname')
                                    ->label('Прізвище пасажира')
                                    ->required(),
                                TextInput::make('phone_number')
                                    ->label('Номер телефону')
                                    ->required(),
                                TextInput::make('email')
                                    ->label('Електронна пошта'),
                                TextInput::make('viber')
                                    ->label('Viber')
                                    ->helperText('Не обовʼязково'),
                                TextInput::make('telegram')
                                    ->label('Telegram'),
                                Forms\Components\Textarea::make('note')
                                    ->label('Примітка'),
                            ])
                            ->minItems(1)
                            ->columns(4),
                    ]),

                Hidden::make('seat_layout')
                    ->id('seat_layout')
                    ->default('[]')
                    ->reactive(),

                Forms\Components\Section::make('Вибір місць')
                    ->aside()
                    ->schema([
                        Livewire::make('App\Http\Livewire\SeatSelector')
                            ->statePath('seat_layout')
                            ->reactive()
                    ])
                    ->columns(1),

                Hidden::make('selected_seat')
                    ->id('selected_seat')
                    ->statePath('selected_seat')
                    ->reactive(),

                Grid::make([
                    'default' => 4,
                ])
                    ->schema([
                        Hidden::make('seat_price')
                            ->id('seat_price')
                            ->reactive()
                            ->afterStateUpdated(fn($state, callable $get, callable $set) => self::recomputePrice($get, $set)),

                        Hidden::make('seat_number')
                            ->id('data.seat_number')
                            ->default(fn(callable $get) => $get('selected_seat'))
                            ->dehydrated(fn($state) => !empty($state))
                            ->reactive(),

                        Select::make('ticket_type')
                            ->label('Тип квитка')
                            ->options([
                                'adult' => 'Дорослий',
                                'child' => 'Дитячий',
                            ])
                            ->default('adult')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function (callable $get, callable $set) {
                                $ticketType = $get('ticket_type') ?? 'adult';
                                $discountId = $get('discount_id');
                                $currencyCode = $get('currency_code') ?? 'UAH';
                                $basePrice = $get('seat_price') ?? $get('base_price') ?? 0;
                                $additionalServices = $get('additional_services') ?? [];

                                $finalPrice = self::calculateTotalPrice($basePrice, $ticketType, $discountId, $additionalServices, $currencyCode);
                                $set('price', $finalPrice);
                            }),

                        Select::make('discount_id')
                            ->label('Знижка')
                            ->options(\App\Models\Discount::all()->pluck('name', 'id'))
                            ->nullable()
                            ->reactive()
                            ->afterStateUpdated(function (callable $get, callable $set) {
                                $ticketType = $get('ticket_type') ?? 'adult';
                                $discountId = $get('discount_id');
                                $currencyCode = $get('currency_code') ?? 'UAH';
                                $basePrice = $get('seat_price') ?? $get('base_price') ?? 0;
                                $additionalServices = $get('additional_services') ?? [];

                                $finalPrice = self::calculateTotalPrice($basePrice, $ticketType, $discountId, $additionalServices, $currencyCode);
                                $set('price', $finalPrice);
                            }),

                        TextInput::make('price')
                            ->label('Ціна')
                            ->required()
                            ->numeric()
                            ->reactive()
                            ->id('data.price'),

                        Select::make('currency_code')
                            ->label('Валюта')
                            ->options(\App\Models\Currency::all()->pluck('name', 'code'))
                            ->default('UAH')
                            ->reactive()
                            ->afterStateUpdated(function (callable $get, callable $set) {
                                $ticketType = $get('ticket_type') ?? 'adult';
                                $discountId = $get('discount_id');
                                $currencyCode = $get('currency_code') ?? 'UAH';
                                $basePrice = $get('seat_price') ?? $get('base_price') ?? 0;
                                $additionalServices = $get('additional_services') ?? [];

                                $finalPrice = self::calculateTotalPrice($basePrice, $ticketType, $discountId, $additionalServices, $currencyCode);
                                $set('price', $finalPrice);
                            }),
                    ]),

                Forms\Components\CheckboxList::make('additional_services')
                    ->label('Додаткові послуги')
                    ->options(\App\Models\AdditionalService::all()->pluck('name', 'id'))
                    ->helperText('Виберіть послуги, які бажаєте додати. Їхня вартість буде додана до загальної суми.')
                    ->reactive()
                    ->afterStateUpdated(function (callable $get, callable $set) {
                        $ticketType = $get('ticket_type') ?? 'adult';
                        $discountId = $get('discount_id');
                        $currencyCode = $get('currency_code') ?? 'UAH';
                        $basePrice = $get('seat_price') ?? $get('base_price') ?? 0;
                        $additionalServices = $get('additional_services') ?? [];

                        $finalPrice = self::calculateTotalPrice($basePrice, $ticketType, $discountId, $additionalServices, $currencyCode);
                        $set('price', $finalPrice);
                    }),

                Hidden::make('base_price')
                    ->default(0)
                    ->required(),
            ])
            ->statePath('data')
            ->model(Booking::class);
    }

    /**
     * Load seat layout for a bus and update the form
     */
    public static function loadBusSeatLayout($busId, $date, $set)
    {
        if (!$busId || !$date) {
            return;
        }

        $bus = Bus::find($busId);
        if (!$bus || !is_array($bus->seat_layout)) {
            $set('seat_layout', json_encode([]));
            return;
        }

        //// ТУТ ЗМІНИ ДЛЯ ПОЗНАЧЕННЯ ЗАЙНЯТИХ МІСЦЬ >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
        // Беремо одразу обидві колонки, виключаємо скасовані/повернені й приводимо до рядків
        $bookings = Booking::where('bus_id', $busId)
            ->whereDate('date', $date)
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->get(['seat_number', 'selected_seat'])
            ->flatMap(function ($b) {
                return [
                    $b->seat_number,
                    $b->selected_seat,
                ];
            })
            ->filter()                    // прибрати null/порожні
            ->map(fn($v) => (string)$v) // привести до рядка, щоб "12" і 12 були однакові
            ->unique()
            ->values()
            ->all();
        //// КІНЕЦЬ ЗМІН <<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<

        $seatLayout = collect($bus->seat_layout)->map(function ($seat) use ($bookings) {
            $num = isset($seat['number']) ? (string)$seat['number'] : null;
            $seat['is_reserved'] = $num !== null && in_array($num, $bookings, true);
            return $seat;
        })->toArray();

        $seatLayoutJson = json_encode($seatLayout);

        if (json_last_error() === JSON_ERROR_NONE) {
            $set('seat_layout', $seatLayoutJson);
            Log::info('Set seat_layout in form state (final)', ['json' => $seatLayoutJson]);
        } else {
            Log::error('JSON Encode Error in loadBusSeatLayout (final)', ['error' => json_last_error_msg()]);
            $set('seat_layout', '[]');
        }

        $set('selected_seat', null);
    }

    /**
     * Доступні дати за from/to — через trips → bus → календар автобуса
     */
    public static function getAvailableDatesByCities(?string $from, ?string $to): array
    {
        if (!$from) return [];

        $fromN = self::normalizePlace($from);
        $toN = $to ? self::normalizePlace($to) : null;

        $start = \Carbon\Carbon::today();
        $end = $start->copy()->addDays(90);

        $busIds = \App\Models\Trip::query()
            ->where('start_location', $fromN)
            ->when($toN, fn($q) => $q->where('end_location', $toN))
            ->pluck('bus_id')->unique()->values()->all();

        if (empty($busIds)) return [];

        $dates = [];
        $buses = \App\Models\Bus::whereIn('id', $busIds)->get();

        foreach ($buses as $bus) {
            foreach ((array)($bus->operation_days ?? []) as $od) {
                $d = is_array($od) ? ($od['date'] ?? null) : null;
                if ($d && $d >= $start->toDateString() && $d <= $end->toDateString()) {
                    $dates[] = $d;
                }
            }

            $weekly = (array)($bus->weekly_operation_days ?? []);
            if ($weekly) {
                $cur = $start->copy();
                while ($cur->lte($end)) {
                    if (self::weeklyMatches($cur, $weekly) &&
                        (method_exists($bus, 'worksOnDate') ? $bus->worksOnDate($cur) : self::fallbackWorksOnDate($bus, $cur))) {
                        $dates[] = $cur->toDateString();
                    }
                    $cur->addDay();
                }
            }
        }

        return array_values(array_unique($dates));
    }

    public static function getAvailableDatesForBus(int $busId): array
    {
        $bus = \App\Models\Bus::find($busId);
        if (!$bus) {   // ✅
            return [];
        }

        $start = \Carbon\Carbon::today();
        $end = $start->copy()->addDays(90);

        $dates = [];

        foreach ((array)($bus->operation_days ?? []) as $od) {
            $d = is_array($od) ? ($od['date'] ?? null) : null;
            if ($d && $d >= $start->toDateString() && $d <= $end->toDateString()) {
                $dates[] = $d;
            }
        }

        $weekly = (array)($bus->weekly_operation_days ?? []);
        if ($weekly) {
            $cur = $start->copy();
            while ($cur->lte($end)) {
                if (method_exists($bus, 'worksOnDate')
                    ? $bus->worksOnDate($cur)
                    : self::fallbackWorksOnDate($bus, $cur)) {
                    $dates[] = $cur->toDateString();
                }
                $cur->addDay();
            }
        }

        return array_values(array_unique($dates));
    }

    protected static function weeklyMatches(Carbon $date, array $weekly): bool
    {
        $wanted = [];
        foreach ($weekly as $w) {
            $n = self::normalizeWeeklyValue($w);
            if ($n) $wanted[] = $n; // ISO 1..7
        }
        if (!$wanted) return false;
        return in_array($date->isoWeekday(), $wanted, true);
    }

    protected static function normalizeWeeklyValue($v): ?int
    {
        if ($v === null) return null;
        if (is_int($v) || ctype_digit((string)$v)) {
            $n = (int)$v;
            return ($n >= 1 && $n <= 7) ? $n : null;
        }
        $s = mb_strtolower(trim((string)$v));

        $map = [
            // EN
            'mon' => 1, 'monday' => 1,
            'tue' => 2, 'tuesday' => 2,
            'wed' => 3, 'wednesday' => 3,
            'thu' => 4, 'thursday' => 4, 'thur' => 4, 'thurs' => 4,
            'fri' => 5, 'friday' => 5,
            'sat' => 6, 'saturday' => 6,
            'sun' => 7, 'sunday' => 7,
            // UA
            'понеділок' => 1, 'вівторок' => 2, 'середа' => 3, 'четвер' => 4, 'пʼятниця' => 5, 'пятниця' => 5, 'субота' => 6, 'неділя' => 7,
            'пн' => 1, 'вт' => 2, 'ср' => 3, 'чт' => 4, 'пт' => 5, 'сб' => 6, 'нд' => 7, 'нед' => 7,
        ];

        return $map[$s] ?? null;
    }

    protected static function fallbackWorksOnDate(\App\Models\Bus $bus, \Carbon\Carbon $date): bool
    {
        $weekly = (array)($bus->weekly_operation_days ?? []);
        if ($weekly && !self::weeklyMatches($date, $weekly)) return false;

        $off = collect((array)($bus->off_days ?? []))
            ->map(fn($x) => is_array($x) ? ($x['date'] ?? null) : null)
            ->filter()->all();

        return !in_array($date->toDateString(), $off, true);
    }

    public static function getAvailableDatesByStops(?int $fromStopId, ?int $toStopId): array
    {
        if (!$fromStopId || !$toStopId) return [];

        $start = \Carbon\Carbon::today();
        $end = $start->copy()->addDays(90);

        // усі автобуси, що реально їздять між цими зупинками
        $busIds = \App\Models\Bus::query()
            ->forStops($fromStopId, $toStopId)
//            ->hasAnySchedule()                   // ✅
            ->pluck('id')->unique()->values()->all();

        if (empty($busIds)) return [];

        $dates = [];
        $buses = \App\Models\Bus::whereIn('id', $busIds)->get();

        foreach ($buses as $bus) {
            foreach ((array)($bus->operation_days ?? []) as $od) {
                $d = is_array($od) ? ($od['date'] ?? null) : null;
                if ($d && $d >= $start->toDateString() && $d <= $end->toDateString()) {
                    $dates[] = $d;
                }
            }

            $weekly = (array)($bus->weekly_operation_days ?? []);
            if ($weekly) {
                $cur = $start->copy();
                while ($cur->lte($end)) {
                    if (
                        self::weeklyMatches($cur, $weekly) &&
                        (method_exists($bus,'worksOnDate') ? $bus->worksOnDate($cur) : self::fallbackWorksOnDate($bus,$cur))
                    ) {
                        $dates[] = $cur->toDateString();
                    }
                    $cur->addDay();
                }
            }
        }

        return array_values(array_unique($dates));
    }

    public static function getAvailableDates($routeId): array
    {
        $from = \Carbon\Carbon::today();
        $to = $from->copy()->addDays(90);

        $dates = [];

        $buses = \App\Models\Bus::where('route_id', $routeId)->get();
        foreach ($buses as $bus) {
            $ops = collect($bus->operation_days ?? [])->pluck('date')->all();
            foreach ($ops as $d) if ($d >= $from->toDateString() && $d <= $to->toDateString()) $dates[] = $d;

            $weekly = (array)($bus->weekly_operation_days ?? []);
            if (!empty($weekly)) {
                $cursor = $from->copy();
                while ($cursor->lte($to)) {
                    if (self::weeklyMatches($cursor, $weekly) &&
                        (method_exists($bus, 'worksOnDate') ? $bus->worksOnDate($cursor) : self::fallbackWorksOnDate($bus, $cursor))) {
                        $dates[] = $cursor->toDateString();
                    }
                    $cursor->addDay();
                }
            }
        }

        return array_values(array_unique($dates));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query->with(['route', 'bus', 'user', 'currency']))
            ->defaultSort('date', 'desc')
            ->striped()

            // ⬇️ ГОЛОВНЕ — фільтри та пошук
            ->filters([
                // Період
                Filter::make('period')
                    ->label('Період')
                    ->form([
                        DatePicker::make('from')->label('З дати'),
                        DatePicker::make('to')->label('По дату'),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        $query->when($data['from'] ?? null, fn($qq, $d) => $qq->whereDate('date', '>=', $d))
                            ->when($data['to'] ?? null, fn($qq, $d) => $qq->whereDate('date', '<=', $d));
                    })
                    ->indicateUsing(function (array $data): array {
                        return array_values(array_filter([
                            !empty($data['from']) ? 'З ' . \Carbon\Carbon::parse($data['from'])->format('d.m.Y') : null,
                            !empty($data['to']) ? 'По ' . \Carbon\Carbon::parse($data['to'])->format('d.m.Y') : null,
                        ]));
                    }),

                // Статус (мультивибір)
                SelectFilter::make('status')
                    ->label('Статус')
                    ->multiple()
                    ->options([
                        'pending' => 'Очікує',
                        'paid' => 'Оплачено',
                        'cancelled' => 'Скасовано',
                        'refunded' => 'Повернено',
                    ])
                    ->indicator('Статус'),

                // Маршрут
                SelectFilter::make('route_id')
                    ->label('Маршрут')
                    ->options(
                        \App\Models\Route::query()
                            ->selectRaw("id, CONCAT(start_point, ' → ', end_point) AS title")
                            ->pluck('title', 'id')
                            ->toArray()
                    )
                    ->indicator('Маршрут'),

                // Автобус
                SelectFilter::make('bus_id')
                    ->label('Автобус')
                    ->options(\App\Models\Bus::pluck('name', 'id')->toArray())
                    ->indicator('Автобус'),

                // Є промокод?
                TernaryFilter::make('has_promo')
                    ->label('Промокод')
                    ->placeholder('—')
                    ->trueLabel('З промокодом')
                    ->falseLabel('Без промокоду')
                    ->queries(
                        fn($query) => $query->whereNotNull('promo_code')->where('promo_code', '<>', ''),
                        fn($query) => $query->where(fn($qq) => $qq->whereNull('promo_code')->orWhere('promo_code', '')),
                        fn($query) => $query,
                    )
                    ->indicator('Промокод'),

                // Є згенерований квиток?
                TernaryFilter::make('has_ticket')
                    ->label('Є квиток')
                    ->placeholder('—')
                    ->trueLabel('Так')
                    ->falseLabel('Ні')
                    ->queries(
                        fn($query) => $query->whereNotNull('ticket_uuid'),
                        fn($query) => $query->whereNull('ticket_uuid'),
                        fn($query) => $query,
                    )
                    ->indicator('Квиток'),

                // Глобальний текстовий пошук
                Filter::make('q')
                    ->label('Пошук')
                    ->form([
                        TextInput::make('value')
                            ->placeholder('Order/UUID, ПІБ, телефон, e-mail...')
                            ->autocomplete(false),
                    ])
                    ->query(function ($query, array $data) {
                        $v = trim((string)($data['value'] ?? ''));
                        if ($v === '') return;

                        $query->where(function ($qq) use ($v) {
                            $qq->where('order_id', 'like', "%{$v}%")
                                ->orWhere('ticket_uuid', 'like', "%{$v}%")
                                ->orWhere('passengers->0->first_name', 'like', "%{$v}%")
                                ->orWhere('passengers->0->last_name', 'like', "%{$v}%")
                                ->orWhere('passengers->0->phone_number', 'like', "%{$v}%")
                                ->orWhere('passengers->0->email', 'like', "%{$v}%")
                                ->orWhereHas('user', fn($uq) => $uq
                                    ->where('name', 'like', "%{$v}%")
                                    ->orWhere('surname', 'like', "%{$v}%")
                                    ->orWhere('email', 'like', "%{$v}%")
                                    ->orWhere('phone', 'like', "%{$v}%"));
                        });
                    })
                    ->indicateUsing(fn(array $data): array => !empty($data['value']) ? ['Пошук: ' . $data['value']] : []
                    ),
            ])
            ->filtersFormColumns(3)
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->columns([
                Split::make([
                    // КОРИСТУВАЧ
                    Stack::make([
                        TextColumn::make('passengerNames')
                            ->label('Користувач')
                            ->description('Користувач', position: 'above')
                            ->icon('heroicon-o-user')
                            ->weight(FontWeight::Bold)
                            ->sortable()
                            ->wrap(),
                        TextColumn::make('passengerPhone')
                            ->label('Телефон')
                            ->icon('heroicon-o-phone')
                            ->size('sm')
                            ->sortable()
                            ->color('gray'),
                        TextColumn::make('passengerEmail')
                            ->label('Пошта')
                            ->icon('heroicon-o-envelope')
                            ->size('sm')
                            ->sortable()
                            ->color('gray')
                            ->wrap(),
                    ])->grow()
                        ->extraAttributes(['class' => 'min-w-[300px]']),

                    // РЕЙС
                    Stack::make([
                        TextColumn::make('route_display')
                            ->label('Рейс')
                            ->description('Рейс', position: 'above')
                            ->icon('heroicon-o-map-pin')
                            ->sortable()
                            ->wrap(),
                        TextColumn::make('date')
                            ->label('Дата поїздки')
                            ->icon('heroicon-o-calendar')
                            ->sortable()
                            ->date('d.m.Y'),
                        TextColumn::make('selected_seat')
                            ->label('Місце')
                            ->icon('heroicon-o-ticket')
                            ->sortable(),
                    ])->grow()
                        ->extraAttributes(['class' => 'min-w-[300px]']),

                    // ОПЛАТА/СТАТУС
                    Stack::make([
                        TextColumn::make('price')
                            ->label('Ціна')
                            ->description('Оплата', position: 'above')
                            ->icon('heroicon-o-banknotes')
                            ->money('UAH')
                            ->sortable(),
                        TextColumn::make('passengerNote')
                            ->label('Коментар')
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->searchable()
                            ->sortable()
                            ->wrap(),
                        BadgeColumn::make('status')
                            ->label('Статус')
                            ->colors([
                                'warning' => 'pending',
                                'success' => 'paid',
                                'danger' => 'cancelled',
                                'gray' => 'refunded',
                            ])
                            ->icons([
                                'heroicon-o-clock' => 'pending',
                                'heroicon-o-check-circle' => 'paid',
                                'heroicon-o-x-circle' => 'cancelled',
                                'heroicon-o-arrow-uturn-left' => 'refunded',
                            ])
                            ->formatStateUsing(fn($state) => match ($state) {
                                'pending' => 'Очікує',
                                'paid' => 'Оплачено',
                                'cancelled' => 'Скасовано',
                                'refunded' => 'Повернено',
                                default => ucfirst($state),
                            }),
                    ])->grow(false)
                        ->extraAttributes(['class' => 'min-w-[260px]']),
                ])->from('md'),
            ])
            ->striped()
            ->actions([
                Tables\Actions\Action::make('build_ticket')
                    ->label('')
                    ->tooltip('Згенерувати квиток (PDF)')
                    ->icon('heroicon-o-qr-code')
                    ->color('success')
                    ->action(function (\App\Models\Booking $record) {
                        app(TicketService::class)->build($record);
                        \Filament\Notifications\Notification::make()
                            ->title('Квиток згенеровано')
                            ->success()->send();
                    }),

                Tables\Actions\Action::make('send_ticket')
                    ->label('')
                    ->tooltip('Надіслати квиток')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn(Booking $record) => filled($record->ticket_pdf_path) && filled($record->passengerEmail))
                    ->action(function (Booking $record) {
                        if (!$record->ticket_pdf_path || !Storage::disk('public')->exists($record->ticket_pdf_path)) {
                            Notification::make()->title('PDF квитка відсутній')->warning()->send();
                            return;
                        }

                        $emails = array_filter(array_map('trim', explode(',', (string)$record->passengerEmail)));
                        if (empty($emails)) {
                            Notification::make()->title('Немає e-mail пасажира')->warning()->send();
                            return;
                        }

                        $pdfBinary = Storage::disk('public')->get($record->ticket_pdf_path);
                        Mail::to($emails)->send(new TicketPdfMail($record, $pdfBinary));

                        Notification::make()
                            ->title('Квиток надіслано: ' . implode(', ', $emails))
                            ->success()->send();
                    }),

                Tables\Actions\Action::make('view_ticket')
                    ->label('')
                    ->tooltip('Переглянути квиток (PDF)')
                    ->icon('heroicon-o-document-text')
                    ->visible(fn(Booking $record) => filled($record->ticket_pdf_path) && filled($record->passengerEmail))
                    ->url(fn(Booking $record) => $record->stable_pdf_url)
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('download_ticket')
                    ->label('')
                    ->tooltip('Скачати квиток (PDF)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn(Booking $record) => filled($record->ticket_pdf_path) && filled($record->passengerEmail))
                    ->color('info')
                    ->url(fn(Booking $record) => $record->stable_pdf_url . '?download=1')
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('open_scanner')
                    ->label('')
                    ->tooltip('Сканер')
                    ->icon('heroicon-o-camera')
                    ->url(route('tickets.scanner'))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('accounting_pdf')
                    ->label('')
                    ->tooltip('Бухгалтерський звіт')
                    ->icon('heroicon-o-document-text')
                    ->color('warning')
                    ->visible(fn($record) => true)
                    ->action(function (\App\Models\Booking $record) {
                        $b = $record->load(['bus', 'route', 'currency', 'user']);

                        $ids = collect($b->additional_service_ids ?? []);
                        if ($ids->isEmpty()) {
                            $raw = $b->additional_services ?? [];
                            if (is_string($raw)) {
                                $raw = json_decode($raw, true) ?: [];
                            }
                            $raw = (is_array($raw) && isset($raw['ids']) && is_array($raw['ids'])) ? $raw['ids'] : $raw;

                            $ids = collect($raw)->flatten()->map(
                                fn($i) => is_array($i) ? ($i['id'] ?? $i['service_id'] ?? null)
                                    : (is_numeric($i) ? (int)$i : null)
                            )->filter()->values();
                        }

                        $additionalServices = $ids->isNotEmpty()
                            ? \App\Models\AdditionalService::whereIn('id', $ids)->get()
                            : collect();

                        $extraSum = (float)$additionalServices->sum('price');
                        $grandTotal = (float)($b->price ?? 0) + $extraSum;

                        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.ticket-accounting', [
                            'b' => $b,
                            'company' => \App\Models\CompanyProfile::first(),
                            'additionalServices' => $additionalServices,
                            'grandTotal' => $grandTotal,
                        ])->setPaper('a4', 'portrait');

                        return response()->streamDownload(fn() => print($pdf->output()),
                            'ticket_accounting_' . $record->id . '.pdf');
                    }),

                Tables\Actions\Action::make('send_payment_link')
                    ->label('')
                    ->icon('heroicon-o-link')
                    ->tooltip('Послати посилання на оплату')
                    ->visible(fn(\App\Models\Booking $record) => in_array($record->status, ['pending']))
                    ->action(function (\App\Models\Booking $record) {
                        $url = route('pay.show', $record->order_id);
                        $msg = "Оплата вашого замовлення:\n{$url}";
                        if ($record->user?->email) {
                            \Mail::raw($msg, fn($m) => $m->to($record->user->email)->subject('Оплата замовлення'));
                            \App\Models\NotificationLog::create(['type' => 'payment_link', 'channel' => 'email', 'booking_id' => $record->id, 'order_id' => $record->order_id, 'to' => $record->user->email, 'status' => 'sent']);
                        }
                        if (class_exists(\App\Services\ViberSender::class) && ($record->passengerPhone ?? $record->user?->phone)) {
                            \App\Services\ViberSender::sendInvoice($record->passengerPhone ?? $record->user->phone, $msg);
                            \App\Models\NotificationLog::create(['type' => 'payment_link', 'channel' => 'viber', 'booking_id' => $record->id, 'order_id' => $record->order_id, 'to' => $record->passengerPhone ?? $record->user->phone, 'status' => 'sent']);
                        }

                        $meta = is_string($record->payment_meta)
                            ? json_decode($record->payment_meta, true) ?: []
                            : ($record->payment_meta ?? []);

                        $tgChatId = $meta['telegram_chat_id'] ?? null;

                        if ($tgChatId && class_exists(\App\Services\TelegramSender::class)) {
                            \App\Services\TelegramSender::sendInvoice($tgChatId, $msg);
                            \App\Models\NotificationLog::create([
                                'type' => 'payment_link',
                                'channel' => 'telegram',
                                'booking_id' => $record->id,
                                'order_id' => $record->order_id,
                                'to' => (string)$tgChatId,
                                'status' => 'sent',
                            ]);
                        } else {
                            $bot = config('services.telegram.bot_username');
                            if ($bot) {
                                $deepLink = "https://t.me/{$bot}?start={$record->order_id}";
                                \Filament\Notifications\Notification::make()
                                    ->title('Telegram: надішліть клієнту цей лінк для прив’язки')
                                    ->body($deepLink)
                                    ->warning()->send();
                            }
                        }

                        \Filament\Notifications\Notification::make()->title('Лінк надіслано')->success()->send();
                    }),

                Action::make('driver_manifest')
                    ->label('')
                    ->icon('heroicon-o-document-text')
                    ->tooltip('Маніфест водія (PDF)')
                    ->form([
                        DatePicker::make('date')->required(),
                        Select::make('bus_id')->label('Автобус')->options(\App\Models\Bus::pluck('name', 'id')->all())->required(),
                    ])
                    ->action(function (array $data) {
                        $bus = \App\Models\Bus::findOrFail($data['bus_id']);
                        $bookings = \App\Models\Booking::with('route')
                            ->where('bus_id', $bus->id)
                            ->whereDate('date', $data['date'])
                            ->orderBy('seat_number')
                            ->get();

                        $rows = $bookings->map(fn($b) => [
                            'seat' => $b->seat_number,
                            'name' => $b->passengerNames,
                            'phone' => $b->passengerPhone,
                            'note' => $b->passengerNote,
                            'status' => $b->status,
                        ])->all();

                        $route = optional($bookings->first()?->route)->start_point . ' - ' . optional($bookings->first()?->route)->end_point;

                        $pdf = Pdf::loadView('reports.driver-manifest', [
                            'rows' => $rows, 'date' => $data['date'], 'bus' => $bus, 'route' => $route
                        ])->setPaper('a4', 'portrait');

                        return response()->streamDownload(fn() => print($pdf->output()),
                            'manifest_' . $data['date'] . '_' . $bus->id . '.pdf');
                    }),

                Action::make('promo_report_csv')
                    ->label('')
                    ->icon('heroicon-o-tag')
                    ->tooltip('Звіт промокодів (CSV)')
                    ->form([
                        DatePicker::make('from')->label('З дати'),
                        DatePicker::make('to')->label('По дату'),
                    ])
                    ->action(function (array $data) {
                        $rows = \App\Models\Booking::query()
                            ->select('promo_code',
                                DB::raw('COUNT(*) as qty'),
                                DB::raw('SUM(price_uah) as sum_uah'),
                                DB::raw('SUM(discount_amount) as discount_uah'))
                            ->when($data['from'] ?? null, fn($q, $d) => $q->whereDate('date', '>=', $d))
                            ->when($data['to'] ?? null, fn($q, $d) => $q->whereDate('date', '<=', $d))
                            ->whereNotNull('promo_code')
                            ->groupBy('promo_code')
                            ->orderBy('qty', 'desc')
                            ->get();

                        $fh = fopen('php://temp', 'w+');
                        fwrite($fh, "\xEF\xBB\xBF");
                        fputcsv($fh, ['Promo', 'Qty', 'SumUAH', 'DiscountUAH']);
                        foreach ($rows as $r) {
                            fputcsv($fh, [$r->promo_code, $r->qty, $r->sum_uah, $r->discount_uah]);
                        }
                        rewind($fh);
                        $csv = stream_get_contents($fh);
                        fclose($fh);
                        return response($csv, 200, [
                            'Content-Type' => 'text/csv; charset=UTF-8',
                            'Content-Disposition' => 'attachment; filename="promo_report.csv"',
                        ]);
                    }),

                ActionGroup::make([
                    Action::make('mark_paid')
                        ->label('Позначити як оплачене')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn(Booking $record) => $record->status !== 'paid')
                        ->action(fn(Booking $record) => $record->markAs('paid')),

                    Action::make('mark_pending')
                        ->label('Повернути в очікування')
                        ->icon('heroicon-o-clock')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn(Booking $record) => $record->status !== 'pending')
                        ->action(fn(Booking $record) => $record->markAs('pending')),

                    Action::make('mark_cancelled')
                        ->label('Скасувати')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn(Booking $record) => $record->markAs('cancelled')),

                    Action::make('mark_refunded')
                        ->label('Повернення коштів')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(fn(Booking $record) => $record->markAs('refunded')),
                ])
                    ->label('Статус оплати')
                    ->icon('heroicon-o-adjustments-vertical'),

                ...GlobalAccount::all()->map(function ($account) {
                    $logoUrl = asset('images/logos/' . $account->id . '.png');

                    return Tables\Actions\Action::make('send_account_' . $account->id)
                        ->label(fn() => new HtmlString(
                            '<img src="' . $logoUrl . '" alt="' . e($account->title) . '" style="height:15px;display:block;" />'
                        ))
                        ->tooltip('Відправити рахунок клієнту')
                        ->extraAttributes(['style' => 'padding:3px 5px; min-width:20px;'])
                        ->color('info')
                        ->action(function ($record) use ($account) {
                            $passenger = $record->passengers[0] ?? null;
                            $route = $record->route_display;
                            $trip = $record->trip;
                            $bus = $record->bus;
                            $accountTitle = $account->title;
                            $accountDetails = $account->details;
                            $bookingId = $record->id;

                            $date = \Carbon\Carbon::parse($record->date)->format('d.m.Y');
                            $time = $trip->departure_time ?? '12:00';
                            $seat = $record->selected_seat ?? '-';
                            $sum = $record->price;
                            $purpose = "Оплата за послуги бронювання $bookingId";

                            $message = <<<MSG
🔔 Продовження бронювання – важлива інформація!

Просимо уважно перевірити дані вашого бронювання:

🚌 Рейс: $date о $time
📍 Маршрут: $route
💺 Місце: №$seat
💵 До сплати: $sum грн

⸻

💳 Реквізити для оплати квитка:

$accountDetails
$accountTitle

📌 Призначення платежу:
$purpose

❗️ Для успішного зарахування коштів обов’язково вказуйте правильне призначення платежу.

📤 Після оплати обов’язково надішліть квитанцію або скріншот про оплату у відповідь на це повідомлення.

⸻

ℹ️ Інформація про багаж та умови повернення квитків:
https://maxbus.com.ua/info/
MSG;

                            \App\Services\ViberSender::sendInvoice(
                                $passenger ? $passenger['phone_number'] : $record->passengerPhone,
                                $message
                            );

                            $meta = is_string($record->payment_meta)
                                ? json_decode($record->payment_meta, true) ?: []
                                : ($record->payment_meta ?? []);
                            $tgChatId = $meta['telegram_chat_id'] ?? null;

                            if ($tgChatId && class_exists(\App\Services\TelegramSender::class)) {
                                \App\Services\TelegramSender::sendInvoice($tgChatId, $message);
                            } else {
                                $bot = config('services.telegram.bot_username');
                                if ($bot) {
                                    $deepLink = "https://t.me/{$bot}?start={$record->order_id}";
                                    \Filament\Notifications\Notification::make()
                                        ->title('Telegram: надішліть клієнту лінк для прив’язки')
                                        ->body($deepLink)
                                        ->warning()->send();
                                }
                            }

                            \Filament\Notifications\Notification::make()
                                ->title("$accountTitle надіслано у Viber і Telegram")
                                ->success()
                                ->send();
                        });
                })->toArray(),
            ])
            ->headerActions([
                Action::make('export_excel')
                    ->label('Експорт (Excel)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->form([
                        DatePicker::make('from')->label('З дати'),
                        DatePicker::make('to')->label('По дату'),
                        Select::make('route_id')
                            ->label('Маршрут')
                            ->options(
                                \App\Models\Route::query()
                                    ->selectRaw("id, CONCAT(start_point, ' → ', end_point) AS title")
                                    ->orderBy('start_point')->orderBy('end_point')
                                    ->pluck('title', 'id')
                            )
                            ->reactive(),
                        Select::make('bus_id')->label('Автобус')
                            ->options(\App\Models\Bus::pluck('name', 'id')->all()),
                        Select::make('status')->label('Статус')
                            ->options(['pending' => 'pending', 'paid' => 'paid', 'cancelled' => 'cancelled', 'refunded' => 'refunded']),
                    ])
                    ->action(function (array $data) {
                        $file = 'bookings_' . now()->format('Ymd_His') . '.xlsx';
                        return Excel::download(
                            new BookingsExport($data['from'] ?? null, $data['to'] ?? null, $data['route_id'] ?? null, $data['bus_id'] ?? null, $data['status'] ?? null),
                            $file
                        );
                    }),

                Action::make('import_passengers')
                    ->label('Імпорт пасажирів (CSV/XLSX)')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->form([
                        Select::make('bus_id')->label('Автобус')->options(\App\Models\Bus::pluck('name', 'id')->all())->required(),
                        DatePicker::make('date')->required(),
                        FileUpload::make('file')->label('Файл')
                            ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                            ->required()
                            ->storeFiles(false),
                    ])
                    ->action(function (array $data, \Filament\Notifications\Notification $n) {
                        $tmp = $data['file']->getRealPath();
                        Excel::import(new PassengersImport((int)$data['bus_id'], $data['date'],
                            \App\Models\Bus::find($data['bus_id'])?->route_id), $tmp);
                        $n::make()->title('Імпорт завершено')->success()->send();
                    })
                    ->modalSubmitActionLabel('Імпортувати')
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulk_mark_paid')
                    ->label('Позначити як оплачені')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(fn($records) => $records->each->markAs('paid')),

                Tables\Actions\BulkAction::make('accounting_csv')
                    ->label('Експорт CSV (бух.)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function ($records) {
                        $rows = [['Дата', 'Маршрут', 'Автобус', 'Місце', 'Пасажир', 'Телефон', 'Email', 'Сума', 'Валюта', 'Статус', 'Метод оплати', 'Примітка', 'BookingID']];
                        foreach ($records as $b) {
                            $rows[] = [
                                $b->date,
                                $b->route_display,
                                optional($b->bus)->name,
                                $b->selected_seat,
                                $b->passengerNames,
                                $b->passengerPhone,
                                $b->passengerEmail,
                                $b->price,
                                optional($b->currency)->code ?? 'UAH',
                                $b->status,
                                $b->payment_method ?? '',
                                $b->passengerNote,
                                $b->id,
                            ];
                        }
                        $fh = fopen('php://temp', 'w+');
                        fwrite($fh, "\xEF\xBB\xBF");
                        foreach ($rows as $r) fputcsv($fh, $r, ',');
                        rewind($fh);
                        $csv = stream_get_contents($fh);
                        fclose($fh);

                        return response($csv, 200, [
                            'Content-Type' => 'text/csv; charset=UTF-8',
                            'Content-Disposition' => 'attachment; filename="accounting_tickets.csv"',
                        ]);
                    }),

                Tables\Actions\BulkAction::make('accounting_zip')
                    ->label('Експорт бух.звітів (ZIP)')
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->action(function (Collection $records) {
                        $zip = new \ZipArchive();
                        $tmp = storage_path('app/tmp_accounting_' . uniqid() . '.zip');

                        $zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

                        foreach ($records as $r) {
                            $r->load(['bus', 'route', 'currency', 'user']);
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.ticket-accounting', [
                                'b' => $r,
                                'company' => company(),
                            ])->setPaper('a4', 'portrait')->output();

                            $zip->addFromString('ticket_' . $r->id . '.pdf', $pdf);
                        }

                        $zip->close();

                        return response()->download($tmp)->deleteFileAfterSend(true);
                    }),

                Tables\Actions\BulkAction::make('remind_now')
                    ->label('Нагадати зараз')
                    ->icon('heroicon-o-bell-alert')
                    ->action(function (\Illuminate\Support\Collection $records) {
                        $sent = 0;
                        foreach ($records as $b) {
                            $departAt = \Carbon\Carbon::parse($b->date . ' ' . $b->trip?->departure_time);
                            $now = now();
                            $kind = $departAt->diffInHours($now) > 3 ? '24h' : '2h';
                            $cmd = app(\App\Console\Commands\SendTripReminders::class);
                            $ref = new \ReflectionClass($cmd);
                            $m = $ref->getMethod('sendForBooking');
                            $m->setAccessible(true);
                            if ($m->invoke($cmd, $b, $kind, $departAt)) $sent++;
                        }
                        \Filament\Notifications\Notification::make()->title("Надіслано: {$sent}")->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookings::route('/'),
            'create' => Pages\CreateBooking::route('/create'),
            'edit' => Pages\EditBooking::route('/{record}/edit'),
        ];
    }

    public static function searchBuses($routeId, $date)
    {
        $dateObj = \Carbon\Carbon::parse($date);
        $buses = \App\Models\Bus::where('route_id', $routeId)->get();

        return \App\Models\Bus::where('route_id', $routeId)
            ->hasAnySchedule()                  // ✅
            ->get()
            ->filter(fn($bus) => $bus->worksOnDate($dateObj));
    }

    private static function calculateFinalPrice($basePrice, $ticketType, $discountId)
    {
        if (!$basePrice) return 0;

        $ticketTypeDiscount = $ticketType === 'child' ? 0.8 : 1.0;
        $finalPrice = $basePrice * $ticketTypeDiscount;

        if ($discountId) {
            $discount = Discount::find($discountId);
            if ($discount) {
                $finalPrice = $finalPrice * (1 - ($discount->percentage / 100));
            }
        }

        return max(round($finalPrice, 2), 0);
    }

    private static function normalizePlace(?string $s): string
    {
        $s = (string)$s;
        $s = str_replace("\xC2\xA0", ' ', $s);
        $s = preg_replace('/\s+/u', ' ', trim($s));
        return $s;
    }

    private static function calculateTotalPrice(
        $seatOrBasePrice, $ticketType, $discountId, $additionalServiceIds = [], $currencyCode = 'UAH'
    )
    {
        if (!$seatOrBasePrice) {
            return 0;
        }

        $ticketTypeDiscount = $ticketType === 'child' ? 0.8 : 1.0;
        $finalPrice = $seatOrBasePrice * $ticketTypeDiscount;

        if ($discountId) {
            $discount = Discount::find($discountId);
            if ($discount) {
                $finalPrice *= (1 - ($discount->percentage / 100));
            }
        }

        $servicesTotal = 0;
        if (!empty($additionalServiceIds)) {
            $servicesTotal = \App\Models\AdditionalService::whereIn('id', $additionalServiceIds)->sum('price');
        }
        $finalPrice += $servicesTotal;

        if ($currencyCode !== 'UAH') {
            $currency = \App\Models\Currency::find($currencyCode);
            $rate = $currency ? $currency->rate : 1;
            $finalPrice = round($finalPrice * $rate, 2);
        } else {
            $finalPrice = round($finalPrice, 2);
        }

        return $finalPrice;
    }

    private static function recomputePrice(callable $get, callable $set): void
    {
        $ticketType = $get('ticket_type') ?? 'adult';
        $discountId = $get('discount_id');
        $currencyCode = $get('currency_code') ?? 'UAH';
        $baseOrSeatPrice = $get('seat_price') ?? $get('base_price') ?? 0;
        $additionalServices = $get('additional_services') ?? [];

        $final = self::calculateTotalPrice(
            $baseOrSeatPrice, $ticketType, $discountId, $additionalServices, $currencyCode
        );
        $set('price', $final);
    }

    public static function getModelLabel(): string
    {
        return 'Бронювання';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Бронювання';
    }

    public static function getNavigationLabel(): string
    {
        return 'Бронювання';
    }
}
