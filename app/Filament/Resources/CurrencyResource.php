<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CurrencyResource\Pages;
use App\Filament\Resources\CurrencyResource\RelationManagers;
use App\Models\Currency;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;


class CurrencyResource extends Resource
{
    protected static ?string $model = Currency::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('code')->label('Код')->required()->maxLength(3)->disabledOn('edit'),
                TextInput::make('name')->label('Назва')->required(),
                TextInput::make('symbol')->label('Символ')->required(),
                TextInput::make('rate')->label('Курс до гривні')->numeric()->required()->minValue(0.0001),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Код')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Назва')
                    ->sortable(),

                Tables\Columns\TextColumn::make('symbol')
                    ->label('Символ')
                    ->wrap(),

                Tables\Columns\TextColumn::make('rate')
                    ->label('Курс до гривні')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCurrencies::route('/'),
            'create' => Pages\CreateCurrency::route('/create'),
            'edit' => Pages\EditCurrency::route('/{record}/edit'),
        ];
    }

    public static function getModelLabel(): string
    {
        return 'Валюта';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Валюта';
    }

    public static function getNavigationLabel(): string
    {
        return 'Валюта';
    }
    public static function getNavigationGroup(): ?string
    {
        return '⚙️ Налаштування';
    }
    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-currency-dollar';
    }
}
