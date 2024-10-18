<?php
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
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TimePicker;
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

                                if ($routeId) {
                                    $availableDates = BookingResource::getAvailableDates($routeId);
                                    $set('available_dates', $availableDates);
                                }
                            }),
                        Select::make('destination_id')
                            ->label('Прибуття у:')
                            ->options(Route::all()->pluck('end_point', 'id'))
                            ->reactive()
                            ->required(),
                        DatePicker::make('date')
                            ->label('Дата поїздки')
                            ->required()
                            ->reactive()
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



