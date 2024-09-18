<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TripResource\Pages;
use App\Filament\Resources\TripResource\RelationManagers;
use App\Models\Trip;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TripResource extends Resource
{
    protected static ?string $model = Trip::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('bus_id')
                    ->relationship('bus', 'name')
                    ->required(),
                Forms\Components\TextInput::make('start_location')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('end_location')
                    ->required()
                    ->maxLength(255),
                Forms\Components\DateTimePicker::make('departure_time')
                    ->required(),
                Forms\Components\DateTimePicker::make('arrival_time')
                    ->required(),
                Forms\Components\TextInput::make('price')
                    ->numeric()
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('bus.name')
                    ->label('Bus')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('start_location')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('end_location')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('departure_time')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('arrival_time')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->sortable()
                    ->money('USD'),
            ])
            ->filters([
                // Додайте фільтри, якщо потрібно
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
}
