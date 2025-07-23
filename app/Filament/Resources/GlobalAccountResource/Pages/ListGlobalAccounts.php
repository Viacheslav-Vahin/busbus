<?php

namespace App\Filament\Resources\GlobalAccountResource\Pages;

use App\Filament\Resources\GlobalAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGlobalAccounts extends ListRecords
{
    protected static string $resource = GlobalAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
