<?php
// app/Filament/Resources/SeatTypeResource.php
namespace App\Filament\Resources;

use App\Filament\Resources\SeatTypeResource\Pages;
use App\Models\SeatType;
use Filament\Forms; use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables; use Filament\Tables\Table;

class SeatTypeResource extends Resource {
    protected static ?string $model = SeatType::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';
    protected static ?string $navigationGroup = 'Довідники';
    protected static ?string $navigationLabel = 'Типи сидінь';

    public static function form(Form $form): Form {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Назва')->required(),
            Forms\Components\TextInput::make('code')->label('Код')->alphaDash()->required()->unique(ignoreRecord: true),
            Forms\Components\Select::make('modifier_type')->label('Тип модифікатора')
                ->options(['percent'=>'% від бази','absolute'=>'Фіксована сума'])->required()->default('percent'),
            Forms\Components\TextInput::make('modifier_value')->numeric()->step('0.01')->default(0)->label('Значення'),
            Forms\Components\TextInput::make('icon')->label('Іконка')->placeholder('heroicon-o-...'),
        ])->columns(2);
    }

    public static function table(Table $table): Table {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Назва')->searchable(),
            Tables\Columns\TextColumn::make('code')->label('Код'),
            Tables\Columns\BadgeColumn::make('modifier_type')->label('Тип')
                ->colors(['primary'=>'percent','warning'=>'absolute']),
            Tables\Columns\TextColumn::make('modifier_value')->label('Значення'),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ])->bulkActions([
            Tables\Actions\DeleteBulkAction::make(),
        ]);
    }

    public static function getPages(): array {
        return [ 'index' => Pages\ManageSeatTypes::route('/'), ];
    }
}

