<?php
// app/Filament/Resources/CmsSettingResource/Pages/EditCmsSetting.php
namespace App\Filament\Resources\CmsSettingResource\Pages;

use App\Filament\Resources\CmsSettingResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditCmsSetting extends EditRecord
{
    protected static string $resource = CmsSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
