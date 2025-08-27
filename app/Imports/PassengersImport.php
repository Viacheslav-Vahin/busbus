<?php

namespace App\Imports;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;

class PassengersImport implements ToCollection, WithHeadingRow
{
    public function __construct(
        protected int $busId,
        protected string $date,
        protected ?int $routeId = null
    ){}

    public function collection(Collection $rows)
    {
        $user = Auth::user() ?? User::first(); // страхівка

        foreach ($rows as $row) {
            $seat = (int) $row['seat'];
            if ($seat <= 0) continue;

            // пропустимо, якщо місце вже зайняте
            $exists = Booking::where('bus_id',$this->busId)
                ->whereDate('date',$this->date)
                ->where('seat_number',$seat)->exists();
            if ($exists) continue;

            Booking::create([
                'trip_id'       => 1,
                'route_id'      => $this->routeId,
                'bus_id'        => $this->busId,
                'selected_seat' => $seat,
                'seat_number'   => $seat,
                'date'          => $this->date,
                'user_id'       => $user?->id,
                'status'        => 'pending',
                'passengers'    => [[
                    'first_name'   => Arr::get($row,'first_name',''),
                    'last_name'    => Arr::get($row,'last_name',''),
                    'phone_number' => Arr::get($row,'phone'),
                    'email'        => Arr::get($row,'email'),
                    'note'         => Arr::get($row,'note'),
                ]],
                'price'         => 0,
                'price_uah'     => 0,
                'currency_code' => 'UAH',
            ]);
        }
    }
}
