<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GlobalAccountResource\Pages;
use App\Filament\Resources\GlobalAccountResource\RelationManagers;
use App\Models\GlobalAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;

class GlobalAccountResource extends Resource
{
    protected static ?string $model = GlobalAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('title')->label('Назва рахунка')->required(),
                Textarea::make('details')->label('Реквізити (текст, IBAN, коментарі...)')->required()->rows(5),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Назва рахунка')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('details')
                    ->label('Реквізити')
                    ->sortable()
                    ->searchable(),
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
            'index' => Pages\ListGlobalAccounts::route('/'),
            'create' => Pages\CreateGlobalAccount::route('/create'),
            'edit' => Pages\EditGlobalAccount::route('/{record}/edit'),
        ];
    }

    public static function getModelLabel(): string
    {
        return 'Рахунки і реквізити';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Рахунки і реквізити';
    }

    public static function getNavigationLabel(): string
    {
        return 'Рахунки і реквізити';
    }

    public static function getNavigationGroup(): ?string
    {
        return '⚙️ Налаштування';
    }
    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-document-text';
    }
}
