<?php

namespace App\Filament\Resources\SeatTypeResource\Pages;

use App\Filament\Resources\SeatTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSeatTypes extends ListRecords
{
    protected static string $resource = SeatTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
