<?php
namespace App\Filament\Resources;

use App\Filament\Resources\StopResource\Pages;
use App\Models\Stop;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class StopResource extends Resource
{
    protected static ?string $model = Stop::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                ->label('Назва зупинки')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Назва зупинки')
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
            'index' => Pages\ListStops::route('/'),
            'create' => Pages\CreateStop::route('/create'),
            'edit' => Pages\EditStop::route('/{record}/edit'),
        ];
    }

    public static function getModelLabel(): string
    {
        return 'Зупинки';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Зупинки';
    }

    public static function getNavigationLabel(): string
    {
        return 'Зупинки';
    }
}
