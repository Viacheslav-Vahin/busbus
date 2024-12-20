<?php

namespace App\Http\Livewire;

use Livewire\Component;

class ManageBooking extends Component
{
//    public $selectedSeat = '';
//    public $price = 0;
//
//    protected $listeners = ['setSelectedSeat'];
//
//    public function setSelectedSeat($seatNumber, $seatPrice)
//    {
//        $this->selectedSeat = $seatNumber;
//        $this->price = $seatPrice;
//    }

    public function render()
    {
        return view('livewire.manage-booking');
    }
}
