<?php
// app/Livewire/SeatSelector.php
namespace App\Livewire;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Action;
use Livewire\Component;
use Illuminate\Support\Facades\Log;

class SeatSelector extends Component implements HasForms, HasActions
{
    use InteractsWithActions;
    use InteractsWithForms;

    public $state = [];
    public $selectedSeat;
    public $seatPrice;

    protected $listeners = ['setSelectedSeat'];

    public function mount($state = [])
    {
        $this->state = $state;
    }

    public function setSelectedSeat($seatNumber, $seatPrice)
    {
        Log::info('setSelectedSeat викликано', ['seatNumber' => $seatNumber, 'seatPrice' => $seatPrice]);
        $this->selectedSeat = $seatNumber;
        $this->seatPrice = $seatPrice;
        $this->emit('seatSelected', $seatNumber, $seatPrice);
    }

    public function setSelectedSeatAction(): Action
    {
        return Action::make('setSelectedSeat')
            ->action(function ($arguments) {
                $this->selectedSeat = $arguments['seatNumber'];
                $this->seatPrice = $arguments['seatPrice'];
                Log::info('setSelectedSeat action виконано', ['seatNumber' => $this->selectedSeat, 'seatPrice' => $this->seatPrice]);
            });
    }

    public function render()
    {
        Log::info('Rendering SeatSelector component', ['state' => $this->state]);
        return view('livewire.seat-selector', ['state' => $this->state]);
    }
}
