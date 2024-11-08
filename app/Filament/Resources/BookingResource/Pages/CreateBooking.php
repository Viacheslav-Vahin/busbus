<?php
namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Booking;

class CreateBooking extends CreateRecord
{
    protected static string $resource = BookingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Оновимо дані перед створенням запису
        $data['seat_number'] = $data['selected_seat'];
        return $data;
    }

    protected function afterCreate(): void
    {
        parent::afterCreate();

        // Логіка після створення бронювання, якщо необхідно
    }
}
