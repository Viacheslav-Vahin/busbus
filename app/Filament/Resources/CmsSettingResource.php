<?php
// app/Filament/Resources/CmsSettingResource.php
namespace App\Filament\Resources;

use App\Models\CmsSetting;
use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Resource;
use App\Filament\Resources\CmsSettingResource\Pages;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;

class CmsSettingResource extends Resource
{
    protected static ?string $model = CmsSetting::class;
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel  = 'Cms Налаштування';
    protected static ?string $navigationGroup  = 'Cms';
    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            TextInput::make('group')->required(),
            TextInput::make('key')->required(),

            Toggle::make('is_json')
                ->label('Зберігати як JSON (key ⇒ value)')
                ->helperText('Увімкни для складних значень. Для URL, email та ін. залишай вимкнено.'),

            // коли JSON
            KeyValue::make('value')
                ->keyLabel('Ключ')
                ->valueLabel('Значення')
                ->visible(fn(Get $get) => (bool)$get('is_json'))
                ->reorderable()
                ->addButtonLabel('Додати пару'),

            // коли текст
            TextInput::make('value')
                ->label('Значення')
                ->visible(fn(Get $get) => !(bool)$get('is_json'))
                ->columnSpanFull(),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('key')->searchable(),
            Tables\Columns\TextColumn::make('group'),
            Tables\Columns\TextColumn::make('updated_at')->since(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCmsSettings::route('/'),
            'create' => Pages\CreateCmsSetting::route('/create'),
            'edit' => Pages\EditCmsSetting::route('/{record}/edit'),
        ];
    }
}
