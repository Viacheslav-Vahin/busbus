<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Models\Route;
use App\Models\Booking;
use App\Filament\Resources\BookingResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;

class CreateBooking extends CreateRecord
{
    protected static string $resource = BookingResource::class;
    protected array $routes = [];

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $booking = new Booking();
        $booking->fill($data);
        $booking->save();

        return $booking;
    }

    public function mount(): void {
        $this->routes = Route::all()->map(function ($route) {
            $start = $route->start_point ?? 'Unknown Start';
            $end = $route->end_point ?? 'Unknown End';
            return [
                'id' => $route->id,
                'name' => $start . ' - ' . $end
            ];
        })->pluck('name', 'id')->toArray();

        $this->form->fill([
            'routes' => $this->routes
        ]);
    }

    public function form(Form $form): Form {
        return $form
            ->schema([
                Select::make('route_id')
                    ->label('Маршрут')
                    ->options($this->routes)
                    ->required(),
                DatePicker::make('date')
                    ->label('Дата поїздки')
                    ->required()
                    ->minDate(now()),
            ]);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('filament.pages.bookings.create-booking', [
            'routes' => $this->routes,
        ]);
    }
}
