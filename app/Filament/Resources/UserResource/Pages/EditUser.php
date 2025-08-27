<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Показати поточну роль у полі Select
        $data['role'] = $this->record->roles()->pluck('name')->first();
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->data['role'] = $data['role'] ?? null;
        unset($data['role']);
        return $data;
    }

    protected function afterSave(): void
    {
        if (!empty($this->data['role'])) {
            $this->record->syncRoles([$this->data['role']]);
        } else {
            $this->record->syncRoles([]);
        }
    }
}
