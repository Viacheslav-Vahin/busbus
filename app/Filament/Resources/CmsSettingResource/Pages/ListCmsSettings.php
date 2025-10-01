<?php
// app/Filament/Resources/CmsSettingResource/Pages/ListCmsSettings.php
namespace App\Filament\Resources\CmsSettingResource\Pages;

use App\Filament\Resources\CmsSettingResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListCmsSettings extends ListRecords
{
    protected static string $resource = CmsSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
