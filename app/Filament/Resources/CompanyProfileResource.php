<?php
// app/Filament/Resources/CompanyProfileResource.php
namespace App\Filament\Resources;

use App\Filament\Resources\CompanyProfileResource\Pages;
use App\Models\CompanyProfile;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;

class CompanyProfileResource extends Resource
{
    protected static ?string $model = CompanyProfile::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel = 'Налаштування компанії';
    protected static ?string $pluralModelLabel = 'Налаштування компанії';
    protected static ?string $modelLabel = 'Налаштування компанії';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Назва компанії')->required(),
            Forms\Components\TextInput::make('edrpou')->label('ЄДРПОУ')->maxLength(12),
            Forms\Components\TextInput::make('iban')->label('IBAN'),
            Forms\Components\TextInput::make('bank')->label('Банк'),
            Forms\Components\TextInput::make('addr')->label('Адреса'),
            Forms\Components\TextInput::make('vat')->label('Статус ПДВ'),
        ])->columns(2);
    }

    // Ховаємо список/створення — це сінглтон
    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function getNavigationUrl(): string
    {
        $id = CompanyProfile::query()->value('id')
            ?? CompanyProfile::query()->create([])->id;

        return static::getUrl('edit', ['record' => $id]);
    }

    public static function getPages(): array
    {
        return [
            // index існує, але лише для редіректу (див. клас нижче)
            'index' => Pages\ListCompanyProfiles::route('/'),
            // edit приймає record
            'edit'  => Pages\EditCompanyProfile::route('/{record}'),
        ];
    }
    public static function getNavigationGroup(): ?string
    {
        return '⚙️ Налаштування';
    }
    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-arrow-up';
    }
}
