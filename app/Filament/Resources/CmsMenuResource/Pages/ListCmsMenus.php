<?php
// app/Filament/Resources/CmsMenuResource/Pages/ListCmsMenus.php
namespace App\Filament\Resources\CmsMenuResource\Pages;

use App\Filament\Resources\CmsMenuResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListCmsMenus extends ListRecords
{
    protected static string $resource = CmsMenuResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
