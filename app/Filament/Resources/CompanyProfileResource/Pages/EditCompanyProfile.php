<?php
// app/Filament/Resources/CompanyProfileResource/Pages/EditCompanyProfile.php
namespace App\Filament\Resources\CompanyProfileResource\Pages;

use App\Filament\Resources\CompanyProfileResource;
use App\Models\CompanyProfile;
use Filament\Resources\Pages\EditRecord;

class EditCompanyProfile extends EditRecord
{
    protected static string $resource = CompanyProfileResource::class;
    protected static ?string $title = 'Налаштування компанії';

    public function mount($record = null): void
    {
        // забезпечуємо існування одиничного запису
        $record = CompanyProfile::query()->first() ?? CompanyProfile::create([
            'name' => env('COMPANY_NAME', 'ТОВ «Максбус»'),
            'edrpou' => env('COMPANY_EDRPOU'),
            'iban' => env('COMPANY_IBAN'),
            'bank' => env('COMPANY_BANK'),
            'addr' => env('COMPANY_ADDR'),
            'vat'  => env('COMPANY_VAT', 'неплатник ПДВ'),
        ]);

        $this->record = $record;
        $this->authorizeAccess();
        $this->fillForm();
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record->getKey()]);
    }
}
