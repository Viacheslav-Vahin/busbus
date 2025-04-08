<?php
////// app/Http/Livewire/SeatSelector.php
////namespace App\Http\Livewire;
////
////use Filament\Actions\Action;
////use Filament\Actions\Concerns\InteractsWithActions;
////use Filament\Actions\Contracts\HasActions;
////use Filament\Forms\Concerns\InteractsWithForms;
////use Filament\Forms\Contracts\HasForms;
////use Illuminate\Support\Facades\Log;
////use Livewire\Component;
////
////class SeatSelector extends Component implements HasForms, HasActions
////{
////    use InteractsWithActions;
////    use InteractsWithForms;
////
////    public $state = [];
////    public $selectedSeat;
////    public $seatPrice;
////
////    protected $listeners = ['setSelectedSeat', 'updateSeatSelectorState'];
////
////    public function updateSeatSelectorState($seatLayout)
////    {
////        Log::info('updateSeatSelectorState called', ['seatLayout' => $seatLayout]);
////        $this->state = $seatLayout;
////    }
////
////    public function mount($state = [])
////    {
////        $this->state = $state;
////        Log::info('Mounting SeatSelector component', ['state' => $state]);
////    }
////
//////    public function setSelectedSeat($seatNumber, $seatPrice)
//////    {
//////        Log::info('setSelectedSeat викликано', ['seatNumber' => $seatNumber, 'seatPrice' => $seatPrice]);
//////        $this->selectedSeat = $seatNumber;
//////        $this->seatPrice = $seatPrice;
//////
//////        // Використовуємо emit для передачі події батьківському компоненту
//////        $this->emit('seatSelected', $seatNumber, $seatPrice);
//////    }
////
////    public function setSelectedSeat($seatNumber, $seatPrice)
////    {
////        Log::info('setSelectedSeat викликано', ['seatNumber' => $seatNumber, 'seatPrice' => $seatPrice]);
////        $this->selectedSeat = $seatNumber;
////        $this->seatPrice = $seatPrice;
////
////        // Використовуємо оновлення стану для батьківського компонента
////        $this->emitUp('handleSeatSelected', $seatNumber, $seatPrice);
////    }
////
////    public function render()
////    {
////        Log::info('Rendering SeatSelector component', ['state' => $this->state]);
////        return view('livewire.seat-selector', ['state' => $this->state]);
////    }
////}
//
//// app/Http/Livewire/SeatSelector.php
//namespace App\Http\Livewire;
//
//use Filament\Actions\Action;
//use Filament\Actions\Concerns\InteractsWithActions;
//use Filament\Actions\Contracts\HasActions;
//use Filament\Forms\Concerns\InteractsWithForms;
//use Filament\Forms\Contracts\HasForms;
//use Illuminate\Support\Facades\Log;
//use Livewire\Component;
//
//class SeatSelector extends Component implements HasForms, HasActions
//{
//    use InteractsWithActions;
//    use InteractsWithForms;
//
//    public $state = [];
//    public $selectedSeat = '';
//    public $seatPrice;
//
//    protected $listeners = ['setSelectedSeat', 'updateSeatSelectorState'];
//
//    public function updateSeatSelectorState($seatLayout)
//    {
//        Log::info('updateSeatSelectorState called', ['seatLayout' => $seatLayout]);
//        $this->state = $seatLayout;
//    }
//
//    public function mount($state = [])
//    {
//        $this->state = $state;
//        Log::info('Mounting SeatSelector component', ['state' => $state]);
//    }
//    public function setSelectedSeat($seatNumber, $seatPrice)
//    {
//        Log::info('Метод setSelectedSeat викликано', ['seatNumber' => $seatNumber, 'seatPrice' => $seatPrice]);
//
//        // Перед оновленням стану
//        Log::info('Поточний стан перед оновленням', ['selectedSeat' => $this->selectedSeat, 'seatPrice' => $this->seatPrice]);
//
//        $this->selectedSeat = $seatNumber;
//        $this->seatPrice = $seatPrice;
//
//        // Використовуємо emit для передачі події батьківському компоненту
//        $this->emitUp('handleSeatSelected', $seatNumber, $seatPrice);
//
//        // Після оновлення стану
//        Log::info('Поточний стан після оновлення', ['selectedSeat' => $this->selectedSeat, 'seatPrice' => $this->seatPrice]);
//    }
//
//
//    public function render()
//    {
//        Log::info('Rendering SeatSelector component', ['state' => $this->state]);
//        return view('livewire.seat-selector', ['state' => $this->state]);
//    }
//}
// app/Http/Livewire/SeatSelector.php

namespace App\Http\Livewire;

use Illuminate\Support\Facades\Log;
use Livewire\Component;

class SeatSelector extends Component
{
    public $seatLayout = [];
    public $selectedSeat = null;
    public $seatPrice = 0;

//    protected $listeners = ['updateSeatLayout'];
    protected $listeners = ['updateSeatLayout', 'updateSeatLayoutData']; // Додаємо 'updateSeatLayoutData'


    // У App\Http\Livewire\SeatSelector.php
    public function mount($statePath = null)
    {
        Log::info('SeatSelector mounting', ['statePath type' => gettype($statePath), 'statePath value' => $statePath]);
        Log::info('$statePath', [$statePath]);

        $this->seatLayout = []; // Ініціалізуємо пустим масивом за замовчуванням

        // Перевіряємо, чи statePath не порожній і є рядком
        if (!empty($statePath) && is_string($statePath)) {
            try {
                $decodedLayout = json_decode($statePath, true); // true для асоціативного масиву

                // Перевірка помилок декодування JSON
                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::error('JSON Decode Error in mount', ['error' => json_last_error_msg(), 'statePath' => $statePath]);
                } else {
                    // Якщо декодування успішне і результат - масив, присвоюємо його
                    if (is_array($decodedLayout)) {
                        $this->seatLayout = $decodedLayout;
                        Log::info('Decoded seat layout in mount', ['seatLayout' => $this->seatLayout]);
                    } else {
                        Log::warning('Decoded statePath is not an array in mount', ['decoded' => $decodedLayout]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Exception decoding seat layout JSON in mount', ['error' => $e->getMessage(), 'statePath' => $statePath]);
            }
        } else {
            Log::warning('SeatSelector mount received empty or non-string statePath', ['statePath' => $statePath]);
        }
    }

//    public function updateSeatLayout($jsonLayout = null)
//    {
////        Log::info('updateSeatLayout called', ['jsonLayout' => $jsonLayout]);
//
//        if (empty($jsonLayout)) {
//            $this->seatLayout = [];
//            return;
//        }
//
//        try {
//            $this->seatLayout = (is_array($jsonLayout) ? $jsonLayout : json_decode($jsonLayout, true)) ?: [];
//            Log::info('2Updated seat layout', ['seatLayout' => $this->seatLayout]);
//            // Видаліть або закоментуйте наступний рядок:
//            // $this->dispatchBrowserEvent('seat-layout-updated');
//        } catch (\Exception $e) {
//            Log::error('Error updating seat layout', ['error' => $e->getMessage()]);
//        }
//    }

    public function updateSeatLayout($jsonLayout = null)
    {
        Log::info('updateSeatLayout called', ['jsonLayout' => $jsonLayout]);

        // Якщо значення порожнє, не оновлюємо $seatLayout
        if ($jsonLayout === '' || is_null($jsonLayout)) {
            return;
        }

        try {
            $this->seatLayout = is_array($jsonLayout) ? $jsonLayout : (json_decode($jsonLayout, true) ?: []);
            Log::info('Updated seat layout', ['seatLayout' => $this->seatLayout]);
            // Видаляємо виклик dispatchBrowserEvent, якщо він не підтримується
        } catch (\Exception $e) {
            Log::error('Error updating seat layout', ['error' => $e->getMessage()]);
        }
    }


    public function updateSeatLayoutData(string $layoutJson) // Отримуємо JSON як рядок
    {
        Log::info('Received updateSeatLayoutData event', ['layoutJson' => $layoutJson]);

        try {
            $decodedLayout = json_decode($layoutJson, true); // Декодуємо в асоціативний масив

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('JSON Decode Error in updateSeatLayoutData', ['error' => json_last_error_msg(), 'layoutJson' => $layoutJson]);
                $this->seatLayout = []; // Встановлюємо порожній масив у разі помилки
            } else {
                // Перевіряємо, чи результат декодування - масив
                if (is_array($decodedLayout)) {
                    $this->seatLayout = $decodedLayout;
                    Log::info('Updated seat layout via event', ['seatLayout' => $this->seatLayout]);
                } else {
                    Log::warning('Decoded layoutJson is not an array in updateSeatLayoutData', ['decoded' => $decodedLayout]);
                    $this->seatLayout = [];
                }
            }
        } catch (\Exception $e) {
            Log::error('Exception decoding JSON in updateSeatLayoutData', ['error' => $e->getMessage(), 'layoutJson' => $layoutJson]);
            $this->seatLayout = [];
        }
        // Livewire автоматично перерендерить компонент після оновлення властивості $seatLayout
    }

    public function selectSeat($seatNumber, $price)
    {
        Log::info('Seat selected', ['seatNumber' => $seatNumber, 'price' => $price]);

        $this->selectedSeat = $seatNumber;
        $this->seatPrice = $price;

        Log::info('Updated component state', ['selectedSeat' => $this->selectedSeat, 'seatPrice' => $this->seatPrice]);

        $this->dispatch('seatSelected', [
            'seatNumber' => $seatNumber,
            'seatPrice' => $price,
        ]);
    }

    public function render()
    {
        $seats = (is_array($this->seatLayout) ? $this->seatLayout : json_decode($this->seatLayout, true)) ?: [];

        Log::info('Rendering SeatSelector component', $seats);
        Log::info('Rendering SeatSelector component', $this->seatLayout);

        return view('livewire.seat-selector', [
            'seats' => $seats,
        ]);
    }


}
