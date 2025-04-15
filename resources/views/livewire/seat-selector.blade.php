{{-- resources/views/livewire/seat-selector.blade.php --}}

<div class="seat-selector-container">
    <div class="mb-4">
        <h3 class="text-lg font-medium">Схема місць автобуса</h3>
        <p class="mb-2">Виберіть місце, клацнувши на ньому. Зайняті місця виділені сірим кольором.</p>
        @if($selectedSeat)
            <div class="p-2 bg-green-100 border border-green-400 rounded">
                <p>Вибране місце: <strong>{{ $selectedSeat }}</strong> ({{ $seatPrice }} грн)</p>
            </div>
        @endif
    </div>

    <div class='seat-layout' style="
         display: grid;
         gap: 10px;
         grid-template-rows: repeat({{collect($seats)->pluck('row')->max() ?? 3}}, auto);
         grid-template-columns: repeat({{collect($seats)->pluck('column')->max() ?? 3}}, 1fr);
         ">
        @if(!empty($seats))
            @foreach($seats as $index => $seat)
                @if($seat['type'] === 'seat')
                    <div
                        wire:key="seat-{{ $index }}"
                        style="grid-row: {{ $seat['row'] }}; grid-column: {{ $seat['column'] }};"
                        class="seat relative p-3 text-center border rounded-t-lg  grid-row: {{ $seat['row'] }};
                grid-column: {{ $seat['column'] }};
                position: relative;
                border: 2.5px solid #ccc;
                border-radius: 18px 18px 6px 6px; {{ isset($seat['is_reserved']) && $seat['is_reserved'] ? 'bg-gray-300 cursor-not-allowed' : 'bg-green-100 hover:bg-green-200 cursor-pointer' }} {{ $selectedSeat == $seat['number'] ? 'bg-green-500 text-white' : '' }} {{ $selectedSeat == $seat['number'] ? 'selected' : '' }}"
                        @if(!isset($seat['is_reserved']) || !$seat['is_reserved'])
                            wire:click="selectSeat('{{ $seat['number'] }}', {{ $seat['price'] ?? 0 }})"
                        @endif
                    >
                        <span class="block font-medium">{{ $seat['number'] ?? 'N/A' }}</span>
                        @if(isset($seat['price']))
                            <span class="text-xs block">{{ $seat['price'] }} грн</span>
                        @endif
                    </div>
                @elseif($seat['type'] === 'driver')
                    <div class="driver relative p-3 text-center bg-blue-100 border rounded-t-lg"
                         style="grid-row: {{ $seat['row'] }}; grid-column: {{ $seat['column'] }};">
                        <span class="block">Водій</span>
                    </div>
                @elseif($seat['type'] === 'wc')
                    <div class="wc relative p-3 text-center bg-purple-100 border rounded-t-lg"
                         style="grid-row: {{ $seat['row'] }}; grid-column: {{ $seat['column'] }};">
                        <span class="block">WC</span>
                    </div>
                @elseif($seat['type'] === 'coffee')
                    <div class="coffee relative p-3 text-center bg-yellow-100 border rounded-t-lg"
                         style="grid-row: {{ $seat['row'] }}; grid-column: {{ $seat['column'] }};">
                        <span class="block">Кава</span>
                    </div>
                @elseif($seat['type'] === 'stuardesa')
                    <div class="stuardesa relative p-3 text-center bg-pink-100 border rounded-t-lg"
                         style="grid-row: {{ $seat['row'] }}; grid-column: {{ $seat['column'] }};">
                        <span class="block">Стюардеса</span>
                    </div>
                @else
                    <div class="empty-space p-3"></div>
                @endif
            @endforeach
        @else
            <div class="h-select-seats col-span-full p-4 border rounded text-center bg-gray-50">
                Виберіть автобус, щоб побачити схему місць
            </div>
        @endif
    </div>
    <input type="hidden" wire:model.defer="selectedSeat" id="selected_seat">

    {{-- Стилі для схеми автобуса --}}
    <style>
        .seat-layout {
            /*display: flex;*/
            /*flex-wrap: wrap;*/
            width: 100%;
            min-width: 300px;
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
            /*width: calc(25% - 10px);*/
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

        .h-select-seats {
            color: #000000;
        }
    </style>
</div>


