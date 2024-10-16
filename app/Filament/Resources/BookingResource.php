<?php
//
//namespace App\Filament\Resources;
//
//use App\Filament\Resources\BookingResource\Pages;
//use App\Filament\Resources\BookingResource\RelationManagers;
//use App\Models\Booking;
//use Filament\Forms;
//use Filament\Forms\Form;
//use Filament\Resources\Resource;
//use Filament\Tables;
//use Filament\Tables\Table;
//use Illuminate\Database\Eloquent\Builder;
//use Illuminate\Database\Eloquent\SoftDeletingScope;
//
//class BookingResource extends Resource
//{
//    protected static ?string $model = Booking::class;
//
//    protected static ?string $navigationIcon = 'heroicon-o-calculator';
//
//    public static function form(Form $form): Form
//    {
//        return $form
//            ->schema([
//                Forms\Components\Select::make('trip_id')
//                    ->relationship('trip', 'start_location')
//                    ->required(),
//                Forms\Components\Select::make('user_id')
//                    ->relationship('user', 'name')
//                    ->required(),
//                Forms\Components\TextInput::make('seat_number')
//                    ->numeric()
//                    ->required(),
//                Forms\Components\TextInput::make('price')
//                    ->numeric()
//                    ->required(),
//                Forms\Components\CheckboxList::make('additional_services')
//                    ->options([
//                        'coffee' => 'Кава',
//                        'blanket' => 'Плед',
//                        'improved_service' => 'Покращений сервіс',
//                    ]),
//            ]);
//    }
//
//    public static function table(Table $table): Table
//    {
//        return $table
//            ->columns([
//                Tables\Columns\TextColumn::make('trip.start_location')
//                    ->label('Trip Start')
//                    ->sortable()
//                    ->searchable(),
//                Tables\Columns\TextColumn::make('user.name')
//                    ->label('User')
//                    ->sortable()
//                    ->searchable(),
//                Tables\Columns\TextColumn::make('seat_number')
//                    ->sortable()
//                    ->searchable(),
//                Tables\Columns\TextColumn::make('price')
//                    ->money('USD'),
//                Tables\Columns\BadgeColumn::make('additional_services')
//                    ->colors([
//                        'primary',
//                    ]),
//            ]);
//    }
//
//    public static function getPages(): array
//    {
//        return [
//            'index' => Pages\ListBookings::route('/'),
//            'create' => Pages\CreateBooking::route('/create'),
//            'edit' => Pages\EditBooking::route('/{record}/edit'),
//        ];
//    }
//}


namespace App\Filament\Resources;

use App\Filament\Resources\BookingResource\Pages;
use App\Models\Booking;
use App\Models\Bus;
use App\Models\Route;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Grid;
use Illuminate\Http\Request;
use Carbon\Carbon;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Grid::make()
                    ->schema([
                        Select::make('route_id')
                            ->label('Виїзд з:')
                            ->options(Route::all()->pluck('start_point', 'id'))
                            ->reactive()
                            ->required()
                            ->afterStateUpdated(function (callable $get, callable $set) {
                                $routeId = $get('route_id');
                                $date = $get('date');

                                if ($routeId) {
                                    $buses = BookingResource::searchBuses($routeId, $date);
                                    $set('buses', $buses->pluck('name', 'id')->toArray());

                                    // Отримуємо доступні дні для рейсу
                                    $availableDates = BookingResource::getAvailableDates($routeId);
                                    $set('available_dates', $availableDates);
                                }
                            }),
                        Select::make('destination_id')
                            ->label('Прибуття у:')
                            ->options(Route::all()->pluck('end_point', 'id'))
                            ->reactive()
                            ->required()
                            ->afterStateUpdated(function (callable $get, callable $set) {
                                $routeId = $get('route_id');
                                $date = $get('date');

                                if ($routeId) {
                                    $buses = BookingResource::searchBuses($routeId, $date);
                                    $set('buses', $buses->pluck('name', 'id')->toArray());

                                    // Отримуємо доступні дні для рейсу
                                    $availableDates = BookingResource::getAvailableDates($routeId);
                                    $set('available_dates', $availableDates);
                                }
                            }),
                        DatePicker::make('date')
                            ->label('Дата поїздки')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function (callable $get, callable $set) {
                                $routeId = $get('route_id');
                                $date = $get('date');

                                if ($routeId) {
                                    $buses = BookingResource::searchBuses($routeId, $date);
                                    $set('buses', $buses->pluck('name', 'id')->toArray());

                                    // Отримуємо доступні дні для рейсу
                                    $availableDates = BookingResource::getAvailableDates($routeId);
                                    $set('available_dates', $availableDates);
                                }
                            })
                            ->disabledDates(function (callable $get) {
                                $availableDates = $get('available_dates');
                                if ($availableDates) {
                                    return function ($date) use ($availableDates) {
                                        return !in_array($date->format('Y-m-d'), $availableDates);
                                    };
                                }
                                return [];
                            })
                            ->minDate(now())
                            ->closeOnDateSelection(true),
                        Actions::make([
                            Action::make('search_buses')
                                ->label('Пошук')
                                ->button()
                                ->color('primary')
                                ->action(function ($get, $set) {
                                    $routeId = $get('route_id');
                                    $date = $get('date');
                                    $buses = BookingResource::searchBuses($routeId, $date);
                                    $set('buses', $buses);
                                })
                                ->requiresConfirmation(false),
                        ]),
                        Select::make('buses')
                            ->label('Доступні автобуси')
                            ->options(fn($get) => $get('buses') ?? [])
                            ->required(),
                    ])
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
                    // Перетворюємо назву дня в дату
                    $dayNumber = Carbon::parse($day)->dayOfWeek;
                    $nextAvailableDate = Carbon::now()->next($dayNumber);
                    $availableDates[] = $nextAvailableDate->format('Y-m-d');
                }
            }

            // Додаємо окремі дати, якщо є
            if (is_array($operationDays)) {
                $availableDates = array_merge($availableDates, $operationDays);
            }
        }

        return $availableDates;
    }



    public static function table(Tables\Table $table): Tables\Table
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

}


