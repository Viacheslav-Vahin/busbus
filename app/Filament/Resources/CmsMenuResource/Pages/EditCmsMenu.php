<?php
// app/Filament/Resources/CmsMenuResource/Pages/EditCmsMenu.php
namespace App\Filament\Resources\CmsMenuResource\Pages;

use App\Filament\Resources\CmsMenuResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditCmsMenu extends EditRecord
{
    protected static string $resource = CmsMenuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
