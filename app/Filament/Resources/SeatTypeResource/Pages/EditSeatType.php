<?php

namespace App\Filament\Resources\SeatTypeResource\Pages;

use App\Filament\Resources\SeatTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSeatType extends EditRecord
{
    protected static string $resource = SeatTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
