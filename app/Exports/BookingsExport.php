<?php

namespace App\Exports;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class BookingsExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(
        protected ?string $from = null,
        protected ?string $to = null,
        protected ?int $routeId = null,
        protected ?int $busId = null,
        protected ?string $status = null,
    ){}

    public function query(): Builder
    {
        return Booking::query()
            ->with(['bus','route','currency'])
            ->when($this->from, fn($q) => $q->whereDate('date','>=',$this->from))
            ->when($this->to,   fn($q) => $q->whereDate('date','<=',$this->to))
            ->when($this->routeId, fn($q) => $q->where('route_id',$this->routeId))
            ->when($this->busId,   fn($q) => $q->where('bus_id',$this->busId))
            ->when($this->status,  fn($q) => $q->where('status',$this->status))
            ->orderBy('date')->orderBy('bus_id')->orderBy('seat_number');
    }

    public function headings(): array
    {
        return [
            'Date', 'Route', 'Bus', 'Seat',
            'Passenger', 'Phone', 'Email',
            'Price', 'Currency', 'Status',
            'Promo', 'DiscountUAH', 'OrderID', 'BookingID',
        ];
    }

    public function map($b): array
    {
        return [
            $b->date,
            $b->route_display ?? optional($b->route)->start_point.' - '.optional($b->route)->end_point,
            optional($b->bus)->name,
            $b->seat_number,
            $b->passengerNames,
            $b->passengerPhone,
            $b->passengerEmail,
            $b->price_uah ?? $b->price,
            $b->currency_code ?? optional($b->currency)->code ?? 'UAH',
            $b->status,
            $b->promo_code,
            $b->discount_amount,
            $b->order_id,
            $b->id,
        ];
    }
}
