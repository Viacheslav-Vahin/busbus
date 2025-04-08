<?php
namespace App\Filament\Resources;

//use Livewire\Livewire;
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
use Filament\Forms\Set;
use Filament\Forms\Components\Livewire;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\ViewField;

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

                                    // Clear dependent fields
                                    $set('date', null);
                                    $set('bus_id', null);
                                    $set('seat_layout', null);
                                    $set('selected_seat', null);
                                    $set('seat_number', null);
                                    $set('price', null);
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

                                        // Load seat layout for the bus
                                        self::loadBusSeatLayout($trip->bus_id, $get('date'), $set);
                                    }
                                }
                            }),

                        DatePicker::make('date')
                            ->label('Дата поїздки')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function (callable $get, callable $set) {
                                $busId = $get('bus_id');
                                $date = $get('date');

                                if ($busId && $date) {
                                    // Reload seat layout with the new date
                                    self::loadBusSeatLayout($busId, $date, $set);
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

                // Bus selection
                Select::make('bus_id')
                    ->label('Виберіть автобус:')
                    ->options(function (callable $get) {
                        $routeId = $get('route_id');
                        $date = $get('date');

                        if ($routeId && $date) {
                            $buses = BookingResource::searchBuses($routeId, $date);
                            return $buses->pluck('name', 'id');
                        }

                        return [];
                    })
                    ->reactive()
                    ->required()
                    ->afterStateUpdated(function (callable $get, callable $set) {
                        $busId = $get('bus_id');
                        $date = $get('date');

                        if ($busId && $date) {
                            // Load seat layout for the selected bus
                            self::loadBusSeatLayout($busId, $date, $set);
                        }
                    }),

                // Hidden field to store seat layout JSON
                Hidden::make('seat_layout')
                    ->id('seat_layout')
                    ->default('[]')
                    ->reactive(),

                // Component for seat selection
                Forms\Components\Section::make('Вибір місць')
                    ->schema([
                        Livewire::make('App\Http\Livewire\SeatSelector')
                            ->statePath('data.seat_layout')
                            ->reactive()
                    ])
                    ->columns(1),

                Hidden::make('selected_seat')
                    ->id('selected_seat')
                    ->statePath('selected_seat')
                    ->reactive(),

//                Hidden::make('selected_seat')
//                    ->id('selected_seat')
//                    ->statePath('data.data.selected_seat')
//                    ->reactive()
//                    ->extraAttributes([
//                        'wire:model.defer' => 'data.data.selected_seat',
//                    ]),

                Hidden::make('seat_number')
                    ->id('seat_number')
                    ->default(fn (callable $get) => $get('selected_seat'))
                    ->dehydrated(fn ($state) => !empty($state))
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

                Hidden::make('base_price')
                    ->required(),

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
                                TextInput::make('note')
                                    ->label('Примітка'),
                            ])
                            ->minItems(1)
                            ->columns(2),
                    ]),
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

        // Отримуємо бронювання для цього автобуса на задану дату
        $bookings = Booking::where('bus_id', $busId)
            ->whereDate('date', $date)
            ->get()
            ->pluck('seat_number')
            ->toArray();

        // Позначаємо заброньовані місця
        $seatLayout = collect($bus->seat_layout)->map(function ($seat) use ($bookings) {
            $seat['is_reserved'] = isset($seat['number']) && in_array($seat['number'], $bookings);
            return $seat;
        })->toArray();

        $seatLayoutJson = json_encode($seatLayout);

        if (json_last_error() === JSON_ERROR_NONE) {
            $set('seat_layout', $seatLayoutJson);
            Log::info('Set seat_layout in form state (final)', ['json' => $seatLayoutJson]);
            // Видаляємо виклик dispatchBrowserEvent тут!
        } else {
            Log::error('JSON Encode Error in loadBusSeatLayout (final)', ['error' => json_last_error_msg()]);
            $set('seat_layout', '[]');
        }

        // Скидаємо вибір місця
        $set('selected_seat', null);
    }

//    public static function loadBusSeatLayout($busId, $date, $set)
//    {
//        if (!$busId || !$date) {
//            return;
//        }
//
//        $bus = Bus::find($busId);
//        if (!$bus || !is_array($bus->seat_layout)) {
//            $set('seat_layout', json_encode([]));
//            return;
//        }
//
//        // Get bookings for this bus on this date to mark reserved seats
//        $bookings = Booking::where('bus_id', $busId)
//            ->whereDate('date', $date)
//            ->get()
//            ->pluck('seat_number')
//            ->toArray();
//
//        // Mark reserved seats in the layout
//        $seatLayout = collect($bus->seat_layout)->map(function ($seat) use ($bookings) {
//            $seat['is_reserved'] = isset($seat['number']) && in_array($seat['number'], $bookings);
//            return $seat;
//        })->toArray();
//
//        // Update the form state
////        $set('seat_layout', (array)json_encode($seatLayout));
////        Log::info('seat_layout_1', (array)json_encode($seatLayout));
////        dispatchBrowserEvent('seat-layout-updated');
//
//        // У BookingResource::loadBusSeatLayout()
//        $seatLayoutJson = json_encode($seatLayout);
//        if (json_last_error() !== JSON_ERROR_NONE) {
//            Log::error('JSON Encode Error in loadBusSeatLayout', ['error' => json_last_error_msg()]);
//            $set('seat_layout', '[]'); // Встановлюємо порожній JSON масив у разі помилки
//        } else {
//            $set('seat_layout', $seatLayoutJson);
//            Log::info('Set seat_layout in form state', ['json' => $seatLayoutJson]); // Логуємо валідний JSON
//        }
//// Важливо: Переконайтеся, що $seatLayout - це дійсно масив перед json_encode
//
//        // Reset selected seat
//        $set('selected_seat', null);
//    }

    /**
     * Get available dates for a route
     */
    public static function getAvailableDates($routeId)
    {
        $buses = Bus::where('route_id', $routeId)->get();
        $availableDates = [];

        foreach ($buses as $bus) {
            $weeklyDays = is_string($bus->weekly_operation_days) ? json_decode($bus->weekly_operation_days, true) : $bus->weekly_operation_days;
            $operationDays = is_string($bus->operation_days) ? json_decode($bus->operation_days, true) : $bus->operation_days;

            // Add weekly days
            if (is_array($weeklyDays)) {
                foreach ($weeklyDays as $day) {
                    $dayOfWeek = Carbon::parse($day)->dayOfWeek;
                    for ($i = 0; $i < 4; $i++) {
                        $nextAvailableDate = Carbon::now()->next($dayOfWeek)->addWeeks($i);
                        $availableDates[] = $nextAvailableDate->format('Y-m-d');
                    }
                }
            }

            // Add specific operation days
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
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Користувач')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('trip.bus.name')
                    ->label('Автобус')
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->label('Дата поїздки')
                    ->date('d.m.Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('selected_seat')
                    ->label('Місце')
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Ціна')
                    ->money('UAH')
                    ->sortable(),
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
        // Format date to check day of week
        $dayOfWeek = date('l', strtotime($date));

        // Find buses for the route that operate on this day
        $buses = Bus::where('route_id', $routeId)
            ->where(function ($query) use ($dayOfWeek, $date) {
                $query->whereJsonContains('weekly_operation_days', $dayOfWeek)
                    ->orWhereJsonContains('operation_days', $date);
            })
            ->get();

        return $buses;
    }

    /**
     * Calculate final price based on ticket type and discounts
     */
    private static function calculateFinalPrice($basePrice, $ticketType, $discountId)
    {
        if (!$basePrice) {
            return 0;
        }

        // Apply ticket type (child ticket has 20% discount)
        $ticketTypeDiscount = $ticketType === 'child' ? 0.8 : 1.0;
        $finalPrice = $basePrice * $ticketTypeDiscount;

        // Apply discount
        if ($discountId) {
            $discount = Discount::find($discountId);
            if ($discount) {
                $finalPrice = $finalPrice * (1 - ($discount->percentage / 100));
            }
        }

        return max(round($finalPrice, 2), 0);
    }

//    /**
//     * Define Livewire listeners for the component
//     */
//    public static function getListeners()
//    {
//        return [
//            'seatSelected' => 'handleSeatSelected',
//        ];
//    }
//
//    /**
//     * Handle seat selection from Livewire component
//     */
//    public function handleSeatSelected($data)
//    {
//        Log::info('Seat selected in Filament form', $data);
//
//        // Update the form with the selected seat data
//        $this->form->fill([
//            'selected_seat' => $data['seatNumber'],
//            'price' => $data['seatPrice'],
//        ]);
//    }
}
