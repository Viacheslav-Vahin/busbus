<?php

namespace App\Filament\Resources\StopResource\Pages;

use App\Filament\Resources\StopResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStop extends EditRecord
{
    protected static string $resource = StopResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
