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

class BusResource extends Resource
{
    protected static ?string $model = Bus::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('seats_count')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('registration_number')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->maxLength(65535)
                    ->nullable(),
                Forms\Components\Placeholder::make('Current Seat Layout')
                    ->content(function ($record) {
                        if ($record) {
                            return view('components.bus-seat-layout', ['bus' => $record]);
                        }
                        return 'No seat layout available';
                    }),
                Forms\Components\Select::make('route_id')
                    ->label('Route')
                    ->relationship('route', 'start_point', fn($query) => $query->addSelect(['start_point', 'end_point']))
                    ->options(function () {
                        return \App\Models\Route::all()->mapWithKeys(function ($route) {
                            return [$route->id => "{$route->start_point} - {$route->end_point}"];
                        });
                    })
                    ->required()
                    ->searchable(),


                Repeater::make('seat_layout')
                    ->label('Розміження сидінь')
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
                    ])
                    ->createItemButtonLabel('Add Seat')
                    ->default([])
                    ->minItems(1)
                    ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('seats_count')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('registration_number')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->limit(50),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBuses::route('/'),
            'create' => Pages\CreateBus::route('/create'),
            'edit' => Pages\EditBus::route('/{record}/edit'),
        ];
    }
}

