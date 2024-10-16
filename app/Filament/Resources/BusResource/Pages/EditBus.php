<?php

namespace App\Filament\Resources\BusResource\Pages;

use App\Filament\Resources\BusResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\Bus;
use App\Models\BusStop;
use App\Models\Route;

class EditBus extends EditRecord
{
    protected static string $resource = BusResource::class;

    /**
     * Handle the creation of a bus record.
     */
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $bus = new Bus();
        $bus->fill($data);
        $bus->weekly_operation_days = $data['weekly_operation_days'] ?? [];
        $bus->save();

        // Sync boarding and dropping points
        $this->syncStops($bus, $data);

        return $bus;
    }

    /**
     * Handle the update of a bus record.
     */
    protected function handleRecordUpdate($record, array $data): \Illuminate\Database\Eloquent\Model
    {
        $record->fill($data);
        $record->weekly_operation_days = $data['weekly_operation_days'] ?? [];
        $record->save();

        $this->syncStops($record, $data);

        return $record;
    }

    /**
     * Sync boarding and dropping points with the bus.
     *
     * @param Bus $bus
     * @param array $data
     */
    protected function syncStops(Bus $bus, array $data): void
    {
//        // Clear existing stops
//        $bus->stops()->detach();
        $bus->stops()->detach();

        // Sync boarding points
        if (!empty($data['boarding_points'])) {
            foreach ($data['boarding_points'] as $boardingPoint) {
                if (isset($boardingPoint['stop_id'], $boardingPoint['time'])) {
                    $bus->stops()->attach($boardingPoint['stop_id'], [
                        'type' => 'boarding',
                        'time' => $boardingPoint['time'],
                    ]);
                }
            }
        }

        // Sync dropping points
        if (!empty($data['dropping_points'])) {
            foreach ($data['dropping_points'] as $droppingPoint) {
                if (isset($droppingPoint['stop_id'], $droppingPoint['time'])) {
                    $bus->stops()->attach($droppingPoint['stop_id'], [
                        'type' => 'dropping',
                        'time' => $droppingPoint['time'],
                    ]);
                }
            }
        }
    }

    /**
     * @param $record
     * @return void
     */
    public function mount($record): void
    {
        try {
            \Log::info('Original mount record value: ', ['record' => $record]);

            // Викликаємо логіку Filament для базового завантаження запису
            parent::mount($record);

            // Завантажуємо запис з бази даних, якщо $record є числом
            if (is_numeric($record)) {
                $record = Bus::query()->find((int)$record);
            }

            if (!$record) {
                abort(404, 'Bus not found');
            }

            \Log::info('Bus record loaded: ', ['busRecord' => $record]);

            // Завантажуємо boardingPoints та droppingPoints
            $boardingPoints = $record->stops()
                ->wherePivot('type', 'boarding')
                ->get(['stops.id as stop_id', 'bus_stops.time'])
                ->map(function ($item) {
                    return [
                        'stop_id' => $item->stop_id,
                        'time' => $item->pivot->time,
                    ];
                })->toArray();

            \Log::info('Loaded boarding points: ', ['boardingPoints' => $boardingPoints]);

            $droppingPoints = $record->stops()
                ->wherePivot('type', 'dropping')
                ->get(['stops.id as stop_id', 'bus_stops.time'])
                ->map(function ($item) {
                    return [
                        'stop_id' => $item->stop_id,
                        'time' => $item->pivot->time,
                    ];
                })->toArray();

            \Log::info('Loaded dropping points: ', ['droppingPoints' => $droppingPoints]);

            // Оновлюємо всі дані форми
            $this->form->fill([
                'name' => $record->name,
                'seats_count' => $record->seats_count,
                'registration_number' => $record->registration_number,
                'description' => $record->description,
                'route_id' => $record->route_id,
                'seat_layout' => $record->seat_layout,
                'weekly_operation_days' => $record->weekly_operation_days,
                'operation_days' => $record->operation_days,
                'off_days' => $record->off_days,
                'boarding_points' => !empty($boardingPoints) ? $boardingPoints : [['stop_id' => '', 'time' => '']],
                'dropping_points' => !empty($droppingPoints) ? $droppingPoints : [['stop_id' => '', 'time' => '']],
            ]);

            \Log::info('Form filled with all data', [
                'name' => $record->name,
                'seats_count' => $record->seats_count,
                'registration_number' => $record->registration_number,
                'description' => $record->description,
                'route_id' => $record->route_id,
                'boarding_points' => $boardingPoints,
                'dropping_points' => $droppingPoints,
            ]);

        } catch (\Exception $e) {
            \Log::error('Error during mount in EditBus: ', ['error' => $e->getMessage()]);
        }
    }


    /**
     * Define the header actions for the bus edit page.
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
