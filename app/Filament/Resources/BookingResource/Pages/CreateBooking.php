<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Booking;
use Illuminate\Support\Facades\Log;

class CreateBooking extends CreateRecord
{
    protected static string $resource = BookingResource::class;

    protected $listeners = [
        'seatSelected' => 'handleSeatSelected',
    ];

//    public function handleSeatSelected($data)
//    {
//        \Log::info('Seat selected in CreateBooking page', $data);
//
//        // Отримуємо поточний стан форми
//        $currentState = $this->form->getState();
//
//        // Об'єднуємо поточний стан з новими даними
//        $this->form->fill(array_merge($currentState, [
//            'selected_seat' => $data['seatNumber'],
//            'seat_number' => $data['seatNumber'],
//            'price'         => $data['seatPrice'],
//        ]));
//    }

//    public function handleSeatSelected($data)
//    {
//        \Log::info('Seat selected in CreateBooking page', $data);
//
//        $currentState = $this->form->getState();
//        \Log::info('$currentState', $currentState);
//
//        $currentState['selected_seat'] = $data['selected_seat'];
//        $currentState['seat_number'] = $data['selected_seat'];
//        $currentState['price'] = $data['seatPrice'];
//
//        \Log::info('$currentState selected_seat', $currentState['selected_seat']);
//        \Log::info('$currentState seat_number', $currentState['seat_number']);
//        \Log::info('$currentState price', $currentState['price']);
//
//
//        $this->form->fill($currentState);
//    }

    public function handleSeatSelected($data)
    {
        \Log::info('Seat selected in CreateBooking page', $data);

        $currentState = $this->form->getState();

        // Використовуємо ключ "seatNumber", а не "selected_seat"
//        $currentState['selected_seat'] = $data['selectedSeat'];
        $currentState['selected_seat'] = $data['seatNumber'];
        $currentState['seat_number'] = $data['seatNumber'];
        $currentState['price'] = $data['seatPrice'];

//        \Log::info('$currentState selected_seat', [$currentState['selected_seat']]);
//        \Log::info('$currentState seat_number', [$currentState['seat_number']]);
//        \Log::info('$currentState price', [$currentState['price']]);
//        \Log::info('$currentState', $currentState);
        $this->form->fill($currentState);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        Log::info('Before save data', $data);
        if (empty($data['seat_number']) && !empty($data['selected_seat'])) {
            $data['seat_number'] = $data['seat_number'] ?? null;
            $data['selected_seat'] = $data['selected_seat'] ?? null;
        }

        return $data;
    }


//    protected function afterCreate(): void
//    {
//        parent::afterCreate();
//
//        // Логіка після створення бронювання, якщо необхідно
//    }
}
