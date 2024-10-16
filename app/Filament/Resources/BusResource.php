<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BusResource\Pages;
use App\Models\Bus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\View;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\CheckboxList;
use App\Models\Stop;
use App\Models\BusStop;
use App\Models\Route;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;

class BusResource extends Resource
{
    protected static ?string $model = Bus::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make([
                    'default' => 1,
                ])
                    ->schema([
                        Tabs::make('Bus Management')
                            ->tabs([
                                Tab::make('Основна інформація')
                                    ->icon('heroicon-m-bell')
                                    ->schema([
                                        Grid::make([
                                            'default' => 2,
                                        ])
                                            ->schema([
                                                Forms\Components\TextInput::make('name')
                                                    ->label('Назва')
                                                    ->required()
                                                    ->maxLength(255),
                                                Forms\Components\TextInput::make('seats_count')
                                                    ->label('Кількість сидінь')
                                                    ->required()
                                                    ->numeric(),
                                                Forms\Components\TextInput::make('registration_number')
                                                    ->label('Номер автобуса')
                                                    ->required()
                                                    ->maxLength(255),
                                                Forms\Components\Textarea::make('description')
                                                    ->label('Опис')
                                                    ->maxLength(65535)
                                                    ->nullable(),
                                                Forms\Components\Select::make('route_id')
                                                    ->label('Маршрут')
                                                    ->relationship('route', 'start_point', fn($query) => $query->addSelect(['start_point', 'end_point']))
                                                    ->options(function () {
                                                        return \App\Models\Route::all()->mapWithKeys(function ($route) {
                                                            return [$route->id => "{$route->start_point} - {$route->end_point}"];
                                                        });
                                                    })
                                                    ->required()
                                                    ->searchable(),
                                            ]),
                                    ]),

                                Tab::make('Розміщення сидінь')
                                    ->schema([
                                        Forms\Components\Placeholder::make('Current Seat Layout')
                                            ->label('Розміщення сидінь')
                                            ->content(function ($record) {
                                                if ($record) {
                                                    return view('components.bus-seat-layout', ['bus' => $record]);
                                                }
                                                return 'No seat layout available';
                                            }),
                                        Repeater::make('seat_layout')
                                            ->label('Розміщення сидінь')
                                            ->schema([
                                                Grid::make([
                                                    'default' => 3,
                                                ])
                                                    ->schema([
                                                        TextInput::make('row')
                                                            ->label('Ряд')
                                                            ->numeric()
                                                            ->required(),
                                                        TextInput::make('column')
                                                            ->label('Місце')
                                                            ->numeric()
                                                            ->required(),
                                                        TextInput::make('number')
                                                            ->label('Номер сидіння')
                                                            ->numeric(),
                                                        Select::make('type')
                                                            ->label('Тип')
                                                            ->options([
                                                                'seat' => 'Сидіння',
                                                                'wc' => 'WC',
                                                                'coffee' => 'Кавомашина',
                                                                'driver' => 'Водій',
                                                                'stuardesa' => 'Стюардеса',
                                                            ])
                                                            ->required(),
                                                        Select::make('ticket_category')
                                                            ->label('Категорія квитка')
                                                            ->options([
                                                                'adult' => 'Дорослий',
                                                                'child' => 'Дитячий',
                                                            ])
                                                            ->default('adult')
                                                            ->required(),
                                                        TextInput::make('price')
                                                            ->label('Ціна за сидіння')
                                                            ->numeric()
                                                            ->minValue(0)
//                                                            ->helperText('Вкажіть ціну для цього сидіння')
                                                            ->required(),
                                                    ])
                                            ])
                                            ->createItemButtonLabel('Add Seat')
                                            ->default([])
                                            ->minItems(1)
                                            ->required(),
                                    ]),

                                Tab::make('Пункти посадки та висадки')
                                    ->schema([
                                        Repeater::make('boarding_points')
                                            ->label('Пункти посадки')
//                                    ->relationship('stops')
                                            ->schema([
                                                Grid::make([
                                                    'default' => 2,
                                                ])
                                                    ->schema([
                                                        Select::make('stop_id')
                                                            ->label('Пункт посадки')
                                                            ->options(Stop::all()->pluck('name', 'id'))
                                                            ->required(),
                                                        TimePicker::make('time')
                                                            ->label('Час')
                                                            ->time()
                                                            ->required(),
                                                    ])
                                            ])
                                            ->default([])
                                            ->createItemButtonLabel('Додати пункт посадки'),

                                        Repeater::make('dropping_points')
                                            ->label('Пункти висадки')
//                                    ->relationship('stops')
                                            ->schema([
                                                Grid::make([
                                                    'default' => 2,
                                                ])
                                                    ->schema([
                                                        Select::make('stop_id')
                                                            ->label('Пункт висадки')
                                                            ->options(Stop::all()->pluck('name', 'id'))
                                                            ->required(),
                                                        TimePicker::make('time')
                                                            ->label('Час')
                                                            ->time()
                                                            ->required(),
                                                    ])
                                            ])
                                            ->default([])
                                            ->createItemButtonLabel('Додати пункт висадки'),
                                    ]),

                                Tab::make('Розклад')
                                    ->schema([
                                        Forms\Components\CheckboxList::make('weekly_operation_days')
                                            ->label('Дні тижня')
                                            ->reactive()
                                            ->options([
                                                'Monday' => 'Понеділок',
                                                'Tuesday' => 'Вівторок',
                                                'Wednesday' => 'Середа',
                                                'Thursday' => 'Четвер',
                                                'Friday' => 'Пятниця',
                                                'Saturday' => 'Субота',
                                                'Sunday' => 'Неділя'
                                            ])
                                            ->default([]),

                                        Forms\Components\Toggle::make('has_operation_days')
                                            ->label('Включити дні роботи')
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, callable $set) {
                                                if (!$state) {
                                                    $set('operation_days', []);
                                                }
                                            }),

                                        Repeater::make('operation_days')
                                            ->schema([
                                                DatePicker::make('date')
                                                    ->label('Date')
                                            ])
                                            ->hidden(fn(callable $get) => !$get('has_operation_days'))
                                            ->default([]),
                                    ]),

                                Tab::make('Вихідні дні')
                                    ->schema([
                                        Forms\Components\Toggle::make('has_off_days')
                                            ->label('Включити вихідні дні')
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, callable $set) {
                                                if (!$state) {
                                                    $set('off_days', []);
                                                }
                                            }),
                                        Repeater::make('off_days')
                                            ->schema([
                                                DatePicker::make('date')
                                                    ->label('Date')
                                            ])
                                            ->hidden(fn(callable $get) => !$get('has_off_days'))
                                            ->default([]),
                                    ]),

                                Tab::make('Ціни на квитки')
                                    ->schema([
                                        Forms\Components\TextInput::make('ticket_price')
                                            ->label('Базова ціна квитка')
                                            ->numeric()
                                            ->required()
                                            ->helperText('Вкажіть ціну квитка для маршруту')
                                            ->default(0)
                                            ->afterStateUpdated(function ($state, callable $set, $get) {
                                                $routeId = $get('route_id');
                                                if ($state !== null && $routeId !== null) {
                                                    Route::where('id', $routeId)->update(['ticket_price' => $state]);
                                                }
                                            })
                                            ->afterStateHydrated(function (callable $set, $record) {
                                                if ($record && $record->route) {
                                                    $set('ticket_price', $record->route->ticket_price);
                                                }
                                            }),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Назва')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('seats_count')
                    ->label('Кількість сидінь')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('registration_number')
                    ->label('Номер автобуса')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Опис')
                    ->limit(50),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Дата створення')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBuses::route('/'),
            'create' => Pages\CreateBus::route('/create'),
            'edit' => Pages\EditBus::route('/{record}/edit'), // Важливо, щоб був коректний шлях {record}/edit
        ];
    }
}
