{{-- resources/views/payment/return.blade.php --}}
@extends('layouts.app')

@section('content')
    <div class="container py-5">
        <h1 class="mb-3">Статус оплати</h1>

        @if(!$orderId)
            <div class="alert alert-warning">Немає ідентифікатора замовлення.</div>
        @elseif($bookings->isEmpty())
            <div class="alert alert-danger">Замовлення {{ $orderId }} не знайдено.</div>
        @else
            <div class="mb-3">
                <b>Order ID:</b> {{ $orderId }}<br>
                <b>Статус:</b>
                @switch($status)
                    @case('paid') <span class="text-success">Оплачено</span> @break
                    @case('cancelled') <span class="text-danger">Скасовано</span> @break
                    @default <span class="text-warning">Очікує підтвердження</span>
                @endswitch
            </div>

            <ul class="list-group mb-4">
                @foreach($bookings as $b)
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Місце {{ $b->seat_number }} · Дата {{ $b->date }}</span>
                        <span>{{ number_format($b->price_uah ?? $b->price, 2, '.', ' ') }} UAH</span>
                    </li>
                @endforeach
            </ul>

            <a href="{{ url('/account/orders') }}" class="btn btn-primary">Мої замовлення</a>
        @endif
    </div>
@endsection
