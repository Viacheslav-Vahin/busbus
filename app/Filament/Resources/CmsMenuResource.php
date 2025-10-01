<?php
// app/Filament/Resources/CmsMenuResource.php
namespace App\Filament\Resources;

use App\Models\CmsMenu;
use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Resource;
use App\Filament\Resources\CmsMenuResource\Pages;
use Filament\Forms\Components\KeyValue;

class CmsMenuResource extends Resource {
    protected static ?string $model = CmsMenu::class;
    protected static ?string $navigationIcon = 'heroicon-o-bars-3';
    protected static ?string $navigationLabel  = 'Cms меню';
    protected static ?string $navigationGroup  = 'Cms';
    public static function form(Forms\Form $form): Forms\Form {
        return $form->schema([
            Forms\Components\TextInput::make('key')->required()->unique(ignoreRecord:true),
            Forms\Components\Repeater::make('items')->schema([
                KeyValue::make('label')
                    ->label('Назва (локалі)')
                    ->keyLabel('locale (uk, en, pl...)')
                    ->valueLabel('Текст')
                    ->addButtonLabel('Додати локаль')
                    ->reorderable(),
                Forms\Components\TextInput::make('title')->label('Назва (plain або локаліз JSON)')->required(),
                Forms\Components\TextInput::make('url')->required(),
                Forms\Components\Repeater::make('children')->schema([
                    Forms\Components\TextInput::make('title')->required(),
                    Forms\Components\TextInput::make('url')->required(),
                ])->collapsed(),
            ])->reorderable(),
        ]);
    }
    public static function table(Tables\Table $table): Tables\Table {
        return $table->columns([
            Tables\Columns\TextColumn::make('id'),
            Tables\Columns\TextColumn::make('key'),
            Tables\Columns\TextColumn::make('updated_at')->since(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCmsMenus::route('/'),
            'create' => Pages\CreateCmsMenu::route('/create'),
            'edit'   => Pages\EditCmsMenu::route('/{record}/edit'),
        ];
    }
}
