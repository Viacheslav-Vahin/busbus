{{-- Відображення плану автобуса в компоненті bus-seat-layout.blade.php--}}
<div class="seat-layout">
    @php
        $seatLayout = is_string($bus->seat_layout) ? json_decode($bus->seat_layout, true) : $bus->seat_layout;
    @endphp

    @if(is_array($seatLayout))
        @foreach($seatLayout as $seat)
            <div class="seat" style="grid-row: {{ $seat['row'] }}; grid-column: {{ $seat['column'] }};">
                @if($seat['type'] === 'seat')
                    Сидіння №{{ $seat['number'] }} <!-- Додаємо відображення номера сидіння -->
                @elseif($seat['type'] === 'wc')
                    WC
                @elseif($seat['type'] === 'driver')
                    Водій
                @elseif($seat['type'] === 'stuardesa')
                    Стюардеса
                @elseif($seat['type'] === 'coffee')
                    Кавомашина
                @else
                    Інше
                @endif
            </div>
        @endforeach
    @else
        <p>Немає доступного перегляду плану автобуса</p>
    @endif

</div>

<style>
    .seat-layout {
        display: grid;
        gap: 10px;
    }

    .seat {
        padding: 5px;
        border: 1px solid #ccc;
        text-align: center;
    }
</style>
