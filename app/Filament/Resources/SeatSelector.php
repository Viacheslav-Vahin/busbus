<?php

namespace App\Forms\Components;

use Filament\Forms\Components\Field;

class SeatSelector extends Field
{
    protected string $view = 'livewire.seat-selector';

    public function setState($state)
    {
        $this->state($state);
        return $this;
    }
}
