<?php
// app/Filament/Resources/BusResource/RelationManagers/SeatsRelationManager.php
namespace App\Filament\Resources\BusResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables; use Filament\Tables\Table;
use Filament\Forms; use Filament\Forms\Form;

class SeatsRelationManager extends RelationManager {
    protected static string $relationship = 'seats';
    protected static ?string $recordTitleAttribute = 'number';
    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('number')->label('№')->required(),
            Forms\Components\Select::make('seat_type_id')
                ->label('Тип сидіння')
                ->relationship('seatType', 'name') // з таблиці seat_types
                ->searchable()->preload()->native(false),
            Forms\Components\TextInput::make('x')->numeric()->label('X'),
            Forms\Components\TextInput::make('y')->numeric()->label('Y'),

            // якщо працюємо через модифікатор від базової ціни:
            Forms\Components\TextInput::make('price_modifier_abs')
                ->label('Коригування ціни (₴)')
                ->numeric()->step('0.01'),
            Forms\Components\TextInput::make('price_modifier_pct')
                ->label('Коригування (%)')
                ->numeric()->step('0.01'),
        ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->label('№')->sortable(),
                Tables\Columns\TextColumn::make('seatType.name')->label('Тип')->toggleable(),
                Tables\Columns\TextColumn::make('x')->label('X')->sortable(),
                Tables\Columns\TextColumn::make('y')->label('Y')->sortable(),
                Tables\Columns\TextColumn::make('price_modifier_abs')->label('Δ₴')->money('UAH')->toggleable(),
                Tables\Columns\TextColumn::make('price_modifier_pct')->label('Δ%')->toggleable(),
            ])
            // швидка масова зміна типу:
            ->bulkActions([
                Tables\Actions\BulkAction::make('setType')
                    ->label('Призначити тип')
                    ->form([
                        Forms\Components\Select::make('seat_type_id')
                            ->relationship('seatType','name')
                            ->searchable()->preload()->native(false)->required(),
                    ])
                    ->action(function ($records, array $data) {
                        foreach ($records as $seat) {
                            $seat->update(['seat_type_id' => $data['seat_type_id']]);
                        }
                    }),
            ]);
    }
}
