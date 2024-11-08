<?php
// BookingResource.php
namespace App\Filament\Resources;

use App\Filament\Resources\BookingResource\Pages;
use App\Models\Booking;
use App\Models\Bus;
use App\Models\Route;
use App\Models\Trip;
use App\Models\Discount;
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
use Filament\Forms\Components\View;
use App\Forms\Components\SeatSelector;

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
                Grid::make()
                    ->schema([
                        Select::make('route_id')
                            ->label('Виїзд з:')
                            ->options(Route::all()->pluck('start_point', 'id'))
                            ->reactive()
                            ->required()
                            ->afterStateUpdated(function (callable $get, callable $set) {
                                $routeId = $get('route_id');

                                if ($routeId) {
                                    $availableDates = BookingResource::getAvailableDates($routeId);
                                    $set('available_dates', $availableDates);

                                    $buses = BookingResource::searchBuses($routeId, $get('date'));
                                    $set('buses', $buses->toArray());
                                }
                            }),

                        Select::make('destination_id')
                            ->label('Прибуття у:')
                            ->options(Route::all()->pluck('end_point', 'id'))
                            ->reactive()
                            ->required(),

                        Select::make('trip_id')
                            ->label('Виберіть поїздку')
                            ->options(Trip::all()->mapWithKeys(function ($trip) {
                                return [$trip->id => $trip->bus->name . ' - ' . $trip->start_location . ' до ' . $trip->end_location];
                            }))
                            ->reactive()
                            ->required()
                            ->afterStateUpdated(function (callable $get, callable $set) {
                                $tripId = $get('trip_id');
                                if ($tripId) {
                                    $trip = Trip::find($tripId);
                                    if ($trip) {
                                        $set('bus_id', $trip->bus_id);
                                        $basePrice = $trip->calculatePrice();
                                        $set('base_price', $basePrice);

                                        $ticketType = $get('ticket_type');
                                        $discountId = $get('discount_id');
                                        $finalPrice = self::calculateFinalPrice($basePrice, $ticketType, $discountId);
                                        $set('price', $finalPrice);
                                    }
                                }
                            }),

                        DatePicker::make('date')
                            ->label('Дата поїздки')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function (callable $get, callable $set) {
                                $routeId = $get('route_id');

                                if ($routeId) {
                                    $availableDates = BookingResource::getAvailableDates($routeId);
                                    $set('available_dates', $availableDates);

                                    $buses = BookingResource::searchBuses($routeId, $get('date'));
                                    $set('buses', $buses->toArray());
                                }
                            })
                            ->disabledDates(function (callable $get) {
                                $availableDates = $get('available_dates');
                                if ($availableDates) {
                                    $today = Carbon::today();
                                    $nextYear = Carbon::today()->addYear();
                                    $disabledDates = [];

                                    while ($today->lte($nextYear)) {
                                        if (!in_array($today->format('Y-m-d'), $availableDates)) {
                                            $disabledDates[] = $today->format('Y-m-d');
                                        }
                                        $today->addDay();
                                    }
                                    return $disabledDates;
                                }
                                return [];
                            })
                            ->minDate(now())
                            ->closeOnDateSelection(true)
                            ->native(false),
                    ]),

                Grid::make()
                    ->schema([
                        Select::make('bus_id')
                            ->label('Виберіть автобус:')
                            ->options(function (callable $get) {
                                $buses = $get('buses');
                                if ($buses) {
                                    return collect($buses)->pluck('name', 'id');
                                }
                                return [];
                            })
                            ->reactive()
                            ->required()
                            ->afterStateUpdated(function (callable $get, callable $set) {
                                $busId = $get('bus_id');
                                $selectedDate = $get('date');
                                Log::info('Bus ID selected', ['bus_id' => $busId, 'selected_date' => $selectedDate]);

                                if ($busId && $busId !== $get('previous_bus_id')) {
                                    $set('previous_bus_id', $busId);
                                    $bus = Bus::find($busId);
                                    if ($bus) {
                                        Log::info('Bus found', ['bus' => $bus->toArray()]);
                                        if ($bus && is_array($bus->seat_layout)) {
                                            // Отримання всіх заброньованих місць для обраного автобуса та дати
                                            $bookings = Booking::where('bus_id', $busId)
                                                ->whereDate('date', $selectedDate)
                                                ->get()
                                                ->pluck('seat_number')
                                                ->toArray();
                                            Log::info('Booked seats retrieved', ['booked_seats' => $bookings]);

                                            // Додавання інформації про заброньовані місця
                                            $seatLayout = collect($bus->seat_layout)->map(function ($seat) use ($bookings) {
                                                if (isset($seat['number']) && in_array($seat['number'], $bookings)) {
                                                    $seat['is_reserved'] = true;
                                                } else {
                                                    $seat['is_reserved'] = false;
                                                }
                                                return $seat;
                                            })->toArray();

                                            $set('seat_layout', $seatLayout);
                                            Log::info('Seat layout updated with reserved status', ['seat_layout' => $seatLayout]);
                                        } else {
                                            $set('seat_layout', []);
                                            Log::warning('Seat layout is not available or is not an array');
                                        }
                                    } else {
                                        Log::info('Bus not found', ['bus_id' => $busId]);
                                    }
                                }
                            })
                    ]),

                Forms\Components\Placeholder::make('seat_layout')
                    ->label('Вибір місць')
                    ->content(function ($state) {
                        if ($state) {
                            return view('livewire.seat-selector', ['state' => $state]);
                        }
                        return 'No seat layout available';
                    })
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state) {
                            $set('price', $state['seatPrice'] ?? 0);
                        }
                    }),

//                Forms\Components\Placeholder::make('seat_layout')
//                    ->label('Вибір місць')
//                    ->content(function ($state) {
//                        if ($state) {
//                            return '<div>' . \Livewire\Livewire::mount('seat-selector', ['state' => $state])->html() . '</div>';
//                        }
//                        return 'No seat layout available';
//                    }),

//                Forms\Components\View::make('livewire.seat-selector')
//                    ->label('Вибір місць')
//                    ->extraAttributes(function (callable $state) {
//                        Log::info('State in seat-selector:', ['state' => $state]);
//                        return ['state' => $state('seat_layout') ?? []];
//                    }),

//                Forms\Components\Livewire::make('seatSelector')
//                    ->component('seat-selector')
//                    ->reactive(),

//                SeatSelector::make('seat_layout')
//                    ->label('Вибір місць')
//                    ->setState(['state' => 'yourSeatLayoutData']),

                TextInput::make('selected_seat')
                    ->label('Вибране місце')
                    ->reactive()
                    ->required(),

                Select::make('ticket_type')
                    ->label('Тип квитка')
                    ->options([
                        'adult' => 'Дорослий',
                        'child' => 'Дитячий',
                    ])
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function (callable $get, callable $set) {
                        $basePrice = $get('base_price');
                        $ticketType = $get('ticket_type');
                        $discountId = $get('discount_id');

                        $finalPrice = self::calculateFinalPrice($basePrice, $ticketType, $discountId);
                        $set('price', $finalPrice);
                    }),

                Select::make('discount_id')
                    ->label('Знижка')
                    ->options(Discount::all()->pluck('name', 'id'))
                    ->nullable()
                    ->reactive()
                    ->afterStateUpdated(function (callable $get, callable $set) {
                        $basePrice = $get('base_price');
                        $ticketType = $get('ticket_type');
                        $discountId = $get('discount_id');

                        $finalPrice = self::calculateFinalPrice($basePrice, $ticketType, $discountId);
                        $set('price', $finalPrice);
                    }),

                TextInput::make('price')
                    ->label('Ціна')
                    ->required()
                    ->numeric()
                    ->reactive(),

                TextInput::make('base_price')
                    ->label('Базова ціна')
                    ->hidden()
                    ->required()
                    ->numeric(),

                Grid::make()
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
                                TextInput::make('note')
                                    ->label('Примітка'),
                            ])
                            ->minItems(1)
                            ->columns(2),
                    ]),
            ]);
    }

    /**
     * Метод для отримання доступних дат для рейсу
     */
    public static function getAvailableDates($routeId)
    {
        $buses = Bus::where('route_id', $routeId)->get();
        $availableDates = [];

        foreach ($buses as $bus) {
            $weeklyDays = is_string($bus->weekly_operation_days) ? json_decode($bus->weekly_operation_days, true) : $bus->weekly_operation_days;
            $operationDays = is_string($bus->operation_days) ? json_decode($bus->operation_days, true) : $bus->operation_days;

            // Додаємо дні тижня
            if (is_array($weeklyDays)) {
                foreach ($weeklyDays as $day) {
                    $dayOfWeek = Carbon::parse($day)->dayOfWeek;
                    for ($i = 0; $i < 4; $i++) { // Наступні 4 тижні
                        $nextAvailableDate = Carbon::now()->next($dayOfWeek)->addWeeks($i);
                        $availableDates[] = $nextAvailableDate->format('Y-m-d');
                    }
                }
            }

            // Додаємо окремі дати, якщо є
            if (is_array($operationDays)) {
                $availableDates = array_merge($availableDates, $operationDays);
            }
        }

        return array_unique($availableDates);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Назва автобуса')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('registration_number')
                    ->label('Номер')
                    ->sortable(),
                Tables\Columns\TextColumn::make('available_seats')
                    ->label('Доступно сидінь')
                    ->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookings::route('/'),
            'create' => Pages\CreateBooking::route('/create'),
            'view' => Pages\ViewSeats::route('/{bus}/seats'),
        ];
    }

    public static function searchBuses($routeId, $date)
    {
        // Форматуємо дату для перевірки дня тижня
        $dayOfWeek = date('l', strtotime($date));

        // Шукаємо автобуси по заданому маршруту, які їздять по заданому дню тижня
        $buses = Bus::where('route_id', $routeId)
            ->where(function ($query) use ($dayOfWeek, $date) {
                $query->whereJsonContains('weekly_operation_days', $dayOfWeek)
                    ->orWhereJsonContains('operation_days', $date);
            })
            ->get();

        return $buses;
    }

    private static function calculateFinalPrice($basePrice, $ticketType, $discountId)
    {
        if (!$basePrice) {
            return 0;
        }

        // Застосувати тип квитка (дитячий квиток має знижку 20%)
        $ticketTypeDiscount = $ticketType === 'child' ? 0.8 : 1.0;
        $finalPrice = $basePrice * $ticketTypeDiscount;

        // Застосувати знижку
        if ($discountId) {
            $discount = Discount::find($discountId);
            if ($discount) {
                $finalPrice = $finalPrice * (1 - ($discount->percentage / 100));
            }
        }

        return max($finalPrice, 0);
    }

    // Додаємо Livewire метод для встановлення обраного місця та ціни
//    public static function setSelectedSeat($seatNumber, $seatPrice)
//    {
//        self::fill([
//            'selected_seat' => $seatNumber,
//            'price' => $seatPrice,
//        ]);
//    }
}
