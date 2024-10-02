<?php

namespace App\Filament\Resources\StopResource\Pages;

use App\Filament\Resources\StopResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStops extends ListRecords
{
    protected static string $resource = StopResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
