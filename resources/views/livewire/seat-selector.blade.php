{{-- resources/views/livewire/seat-selector.blade.php --}}
{{-- Підключаємо Livewire --}}
{{-- Підключаємо альпайн --}}
{{--<script src="https://cdn.jsdelivr.net/npm/alpinejs@2.8.2/dist/alpine.min.js"></script>--}}

<div class="seat-selector">
    <div x-data="{ selectedSeat: '' }" id="bus-seat-layout" class="seat-layout">
        @foreach ($state as $seat)
            @if ($seat['type'] === 'seat')
                <div class="seat"
                     wire:key="seat-{{ $seat['number'] }}"
                     :class="{ 'reserved': {{ json_encode($seat['is_reserved'] ?? false) }}, 'selected': selectedSeat === '{{ $seat['number'] }}' }"
                     @click="if (!{{  json_encode($seat['is_reserved'] ?? false) }}) {
                     console.log('Selected Seat:', '{{ $seat['number'] }}', 'Price:', '{{ $seat['price'] }}');
         selectedSeat = '{{ $seat['number'] }}';
         $wire.call('setSelectedSeat', '{{ $seat['number'] }}', '{{ $seat['price'] }}');
     }"
                     data-seat-number="{{ $seat['number'] }}"
                     data-price="{{ $seat['price'] }}">
                    {{ $seat['number'] }}
                </div>

            @elseif ($seat['type'] === 'driver')
                <div class="driver">Водій</div>
            @elseif ($seat['type'] === 'wc')
                <div class="wc">WC</div>
            @elseif ($seat['type'] === 'coffee')
                <div class="coffee">Кавовий куточок</div>
            @elseif ($seat['type'] === 'stuardesa')
                <div class="coffee">Стюардеса</div>
            @endif
        @endforeach

        <input type="hidden" id="selected-seat" name="selected_seat" x-model="selectedSeat">
    </div>

    {{-- Стилі для схеми автобуса --}}
    <style>
        .seat-layout {
            display: flex;
            flex-wrap: wrap;
            width: 300px;
        }

        .seat:after, .driver:after, .wc:after, .coffee:after {
            content: "";
            position: absolute;
            height: 18px;
            width: calc(100% + 2.5px * 2);
            bottom: calc(2.5px * -1);
            left: calc(2.5px * -1);
            border: solid 2.5px;
            border-radius: 9px 9px 6px 6px;
        }

        .seat, .driver, .wc, .coffee {
            padding: 10px;
            padding-bottom: 15px;
            margin: 5px;
            text-align: center;
            position: relative;
            border: 2.5px solid #ccc;
            border-radius: 18px 18px 6px 6px;
        }

        .seat {
            cursor: pointer;
        }

        .reserved {
            background-color: #d3d3d3;
            position: relative;
            border: 2.5px solid #ccc;
            border-radius: 18px 18px 6px 6px;
            cursor: not-allowed;
        }

        .seat.selected {
            background-color: #4caf50;
            position: relative;
            border: 2.5px solid #ccc;
            border-radius: 18px 18px 6px 6px;
            color: white;
        }
    </style>
    {{-- Вставляємо Livewire скрипти --}}
{{--    @livewireScripts--}}
{{--    @livewireStyles--}}
</div>


