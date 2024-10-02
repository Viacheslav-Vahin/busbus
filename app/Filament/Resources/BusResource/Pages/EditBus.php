<?php

namespace App\Filament\Resources\BusResource\Pages;

use App\Filament\Resources\BusResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBus extends EditRecord
{
    protected static string $resource = BusResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $bus = new Bus();
        $bus->fill($data);
        $bus->weekly_operation_days = $data['weekly_operation_days'] ?? [];
        $bus->save();

        return $bus;
    }

    protected function handleRecordUpdate($record, array $data): \Illuminate\Database\Eloquent\Model
    {
        $record->fill($data);
        $record->weekly_operation_days = $data['weekly_operation_days'] ?? [];
        $record->save();

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
