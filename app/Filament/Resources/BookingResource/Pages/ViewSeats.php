<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use App\Models\Bus;
use Filament\Resources\Pages\Page;

class ViewSeats extends Page
{
    protected static string $resource = BookingResource::class;

    protected static string $view = 'filament.resources.booking.view-seats';

    public $bus;

    public function mount(Bus $bus)
    {
        $this->bus = $bus;
    }

    public function getHeading(): string
    {
        return "Оберіть місце в автобусі: {$this->bus->name}";
    }
}
