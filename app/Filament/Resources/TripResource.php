<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TripResource\Pages;
use App\Models\Trip;
use App\Models\Route;
use App\Models\Bus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;

class TripResource extends Resource
{
    protected static ?string $model = Trip::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('bus_id')
                    ->label('Автобус')
                    ->options(Bus::all()->pluck('name', 'id'))
                    ->required(),
                Select::make('route_id')
                    ->label('Маршрут')
                    ->options(Route::all()->mapWithKeys(function ($route) {
                        return [$route->id => $route->start_point . ' - ' . $route->end_point];
                    }))
                    ->reactive()
                    ->required()
                    ->afterStateUpdated(function (callable $set, $state) {
                        $route = Route::find($state);
                        if ($route) {
                            $set('start_location', $route->start_point);
                            $set('end_location', $route->end_point);
                        }
                    }),
                TextInput::make('start_location')
                    ->label('Початкова локацiя')
                    ->required()
                    ->disabled()
                    ->dehydrated(),
                TextInput::make('end_location')
                    ->label('Кiнцева локацiя')
                    ->required()
                    ->disabled()
                    ->dehydrated(),
                DateTimePicker::make('departure_time')
                    ->label('Час вiдправлення')
                    ->required(),
                DateTimePicker::make('arrival_time')
                    ->label('Час прибуття')
                    ->required(),
                TextInput::make('price')
                    ->label('Цiна квитка')
                    ->required()
                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('bus.name')
                    ->label('Автобус')
                    ->sortable(),
                Tables\Columns\TextColumn::make('route.start_point')
                    ->label('Початкова локацiя')
                    ->sortable(),
                Tables\Columns\TextColumn::make('route.end_point')
                    ->label('Кiнцева локацiя')
                    ->sortable(),
                Tables\Columns\TextColumn::make('departure_time')
                    ->label('Час вiдправлення')
                    ->sortable(),
                Tables\Columns\TextColumn::make('arrival_time')
                    ->label('Час прибуття')
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Цiна квитка')
                    ->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrips::route('/'),
            'create' => Pages\CreateTrip::route('/create'),
            'edit' => Pages\EditTrip::route('/{record}/edit'),
        ];
    }

    public static function getModelLabel(): string
    {
        return 'Маршрути';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Маршрути';
    }

    public static function getNavigationLabel(): string
    {
        return 'Маршрути';
    }
}
