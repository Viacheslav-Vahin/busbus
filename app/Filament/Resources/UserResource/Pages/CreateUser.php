<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // роль заберемо окремо, щоб не писати у колонку users.role
        $this->data['role'] = $data['role'] ?? null;
        unset($data['role']);
        return $data;
    }

    protected function afterCreate(): void
    {
        if (!empty($this->data['role'])) {
            $this->record->syncRoles([$this->data['role']]);
        }
    }
}
