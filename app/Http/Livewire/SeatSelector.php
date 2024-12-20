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

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class SeatSelector extends Component implements HasForms, HasActions
{
    use InteractsWithActions;
    use InteractsWithForms;

    public $state = [];
    public $selectedSeat = '';
    public $seatPrice;

    protected $listeners = ['setSelectedSeat', 'updateSeatSelectorState'];

    public function updateSeatSelectorState($seatLayout)
    {
        Log::info('updateSeatSelectorState called', ['seatLayout' => $seatLayout]);
        $this->state = $seatLayout;
    }

    public function mount($state = [])
    {
        Log::info('Mounting SeatSelector component', ['initial_state' => $state]);
        $this->state = $state;

        if (!empty($this->state)) {
            $seats = array_filter($this->state, fn($seat) => $seat['type'] === 'seat' && !($seat['is_reserved'] ?? false));

            if (!empty($seats)) {
                $randomSeat = $seats[array_rand($seats)];
                $this->selectedSeat = $randomSeat['number'] ?? '';
                $this->seatPrice = $randomSeat['price'] ?? 0;

                Log::info('Рандомно вибране сидіння при завантаженні', [
                    'selectedSeat' => $this->selectedSeat,
                    'seatPrice' => $this->seatPrice,
                ]);
            }
        } else {
            Log::warning('Стан $state порожній при завантаженні компонента.');
        }
    }

    public function setSelectedSeat($seatNumber, $seatPrice)
    {
        Log::info('Метод setSelectedSeat викликано', ['seatNumber' => $seatNumber, 'seatPrice' => $seatPrice]);

        // Перед оновленням стану
        Log::info('Поточний стан перед оновленням', ['selectedSeat' => $this->selectedSeat, 'seatPrice' => $this->seatPrice]);

        $this->selectedSeat = $seatNumber;
        $this->seatPrice = $seatPrice;

        // Використовуємо emit для передачі події батьківському компоненту
        $this->emitUp('handleSeatSelected', $seatNumber, $seatPrice);

        // Після оновлення стану
        Log::info('Поточний стан після оновлення', ['selectedSeat' => $this->selectedSeat, 'seatPrice' => $this->seatPrice]);
    }

    public function render()
    {
        Log::info('Rendering SeatSelector component', ['state' => $this->state]);
        return view('livewire.seat-selector', ['state' => $this->state]);
    }
}
