<?php

namespace App\Filament\Resources\GlobalAccountResource\Pages;

use App\Filament\Resources\GlobalAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGlobalAccount extends EditRecord
{
    protected static string $resource = GlobalAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
