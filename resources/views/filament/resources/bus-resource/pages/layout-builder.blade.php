{{-- resources/views/filament/resources/bus-resource/pages/layout-builder.blade.php --}}
<x-filament-panels::page>
    <style>
        #grid {
            /* параметри сітки; значення оновлюємо з Alpine */
            --cols: 24; /* кількість колонок */
            --rows: 8; /* кількість рядів   */
            --cell: 52px; /* розмір клітинки   */
            --seat-scale: .85; /* масштаб сидіння всередині клітинки */

            width: calc(var(--cols) * var(--cell));
            height: calc(var(--rows) * var(--cell));
        }

        .layout-grid {
            grid-template-columns: repeat(var(--cols), var(--cell));
            grid-template-rows: repeat(var(--rows), var(--cell));
        }

        .layout-grid > div {
            border: 1px solid rgba(255, 255, 255, .08);
        }

        /* сидіння — по центру клітинки, квадрат масштабується seat-scale */
        .seat {
            position: absolute;
            width: calc(var(--cell) * var(--seat-scale));
            height: calc(var(--cell) * var(--seat-scale));
            left: calc(var(--cell) * (var(--x) + .5));
            top: calc(var(--cell) * (var(--y) + .5));
            transform: translate(-50%, -50%);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: .35rem;
            font-size: 12px;
            font-weight: 700;
            line-height: 1;
            background: rgb(245, 128, 11);
            border: 1px solid rgba(255, 255, 255, .25);
            box-shadow: 0 1px 2px rgba(0, 0, 0, .25);
            user-select: none;
            cursor: grab;
        }

        /* сервісні елементи — прямокутники w×h від верхнього-лівого кута клітинки */
        .el {
            position: absolute;
            left: calc(var(--cell) * var(--x));
            top: calc(var(--cell) * var(--y));
            width: calc(var(--cell) * var(--w));
            height: calc(var(--cell) * var(--h));
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(245, 158, 11, .25);
            border: 1px solid rgba(245, 158, 11, .55);
            border-radius: .35rem;
            font-size: 14px;
            user-select: none;
            cursor: grab;
        }
    </style>

    <div x-data="seatLayout()" x-init="init()" class="space-y-3">
        <div class="flex items-center justify-between">
            <div class="text-sm text-gray-500">
                Перетягніть — об’єкт «прилипне» до найближчої клітинки при відпусканні.
            </div>
            <div class="flex items-center gap-2">
                <button type="button" class="px-2 py-1 rounded border" x-on:click="zoomOut()">−</button>
                <span class="text-sm tabular-nums" x-text="cell + 'px'"></span>
                <button type="button" class="px-2 py-1 rounded border" x-on:click="zoomIn()">+</button>
            </div>
        </div>

        <div class="overflow-auto"> {{-- дозволяє прокрутку, якщо сітка більша за екран --}}
            <div
                id="grid"
                x-ref="grid"
                class="relative border rounded"
                x-bind:style="`--cols:${cols};--rows:${rows};--cell:${cell}px;`"
                x-on:dragover.prevent="trackPointer($event)"
            >
                {{-- Сітка --}}
                <div class="absolute inset-0 grid layout-grid">
                    @for($y=0;$y<8;$y++)
                        @for($x=0;$x<24;$x++)
                            <div></div>
                        @endfor
                    @endfor
                </div>

                {{-- Елементи салону --}}
                @foreach($this->elements as $e)
                    <div class="el"
                         style="--x:{{ $e['x']??0 }};--y:{{ $e['y']??0 }};--w:{{ $e['w']??1 }};--h:{{ $e['h']??1 }};"
                         draggable="true"
                         data-id="{{ $e['id'] }}"
                         x-on:dragstart="startDragEl($event)"
                         x-on:dragend="endDragEl($event)">
                        @php
                            $emoji = ['wc'=>'🚻','coffee'=>'☕','driver'=>'🚍','stuardesa'=>'🧑‍✈️','stairs'=>'🪜','exit'=>'🚪'];
                        @endphp
                        {{ $emoji[$e['type']] ?? strtoupper($e['type']) }}
                    </div>
                @endforeach

                <!-- Сидіння -->
                @foreach($this->seats as $s)
                    <div class="seat"
                         style="--x:{{ $s['x']??0 }};--y:{{ $s['y']??0 }};"
                         draggable="true"
                         data-id="{{ $s['id'] }}"
                         x-on:dragstart="startDragSeat($event)"
                         x-on:dragend="endDragSeat($event)"
                         x-on:contextmenu.prevent="openTypeMenu($event, '{{ $s['id'] }}')">
                        {{ $s['number'] }}
                    </div>
                @endforeach

                <!-- Меню вибору типу -->
                <div x-show="menu.open" x-cloak
                     class="fixed z-50 border rounded shadow bg-white dark:bg-gray-900"
                     x-bind:style="`left:${menu.x}px;top:${menu.y}px;`"
                     x-on:click.outside="menu.open=false"
                     x-on:keydown.escape.window="menu.open=false">
                    @foreach($this->seatTypes as $t)
                        <button type="button" class="block w-full text-left px-3 py-2 hover:bg-gray-50"
                                x-on:click="call('setSeatType', menu.seatId, {{ $t['id'] }}); menu.open=false">
                            {{ $t['name'] }}
                        </button>
                    @endforeach
                    <button type="button" class="block w-full text-left px-3 py-2 hover:bg-gray-50"
                            x-on:click="call('setSeatType', menu.seatId, null); menu.open=false">
                        Без типу
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function seatLayout() {
            return {
                cols: 24, rows: 8,
                cell: 56, minCell: 36, maxCell: 96,
                wireId: null, pointerX: 0, pointerY: 0, gridRect: null,
                seatId: null, elId: null,

                // ✨ ДОДАНО: стан контекстного меню
                menu: { open: false, x: 0, y: 0, seatId: null },

                init() {
                    const root = this.$root.closest('[wire\\:id]');
                    this.wireId = root ? root.getAttribute('wire:id') : null;
                    this.updateRect();
                    window.addEventListener('resize', () => this.updateRect(), { passive: true });
                    this.$watch('cell', () => this.updateRect());
                },

                zoomIn()  { this.cell = Math.min(this.cell + 8, this.maxCell); },
                zoomOut() { this.cell = Math.max(this.cell - 8, this.minCell); },

                updateRect() { this.gridRect = this.$refs.grid.getBoundingClientRect(); },
                trackPointer(e) { this.pointerX = e.clientX; this.pointerY = e.clientY; },

                // ✨ ДОДАНО: відкриття меню та хелпер виклику Livewire
                openTypeMenu(e, id) {
                    this.menu = { open: true, x: e.clientX, y: e.clientY, seatId: parseInt(id) };
                },
                call(method, ...args) {
                    const cmp = this.wireId ? Livewire.find(this.wireId) : null;
                    if (cmp) return cmp.call(method, ...args);
                },

                // drag seats
                startDragSeat(e) { this.seatId = e.target.dataset.id; this.updateRect(); },
                async endDragSeat(e) {
                    if (!this.seatId || !this.gridRect) return;
                    let x = Math.round(((e.clientX - this.gridRect.left) / this.gridRect.width)  * (this.cols - 1));
                    let y = Math.round(((e.clientY - this.gridRect.top)  / this.gridRect.height) * (this.rows - 1));
                    x = Math.max(0, Math.min(this.cols - 1, x));
                    y = Math.max(0, Math.min(this.rows - 1, y));

                    // ✨ миттєво оновлюємо позицію через CSS-перемінні, щоб не "стрибало"
                    e.target.style.setProperty('--x', x);
                    e.target.style.setProperty('--y', y);

                    if (this.wireId) await Livewire.find(this.wireId).call('savePosition', this.seatId, x, y);
                    this.seatId = null;
                },

                // drag elements
                startDragEl(e) { this.elId = e.target.dataset.id; this.updateRect(); },
                async endDragEl(e) {
                    if (!this.elId || !this.gridRect) return;
                    let x = Math.round(((e.clientX - this.gridRect.left) / this.gridRect.width)  * (this.cols - 1));
                    let y = Math.round(((e.clientY - this.gridRect.top)  / this.gridRect.height) * (this.rows - 1));
                    x = Math.max(0, Math.min(this.cols - 1, x));
                    y = Math.max(0, Math.min(this.rows - 1, y));

                    // ✨ теж оновлюємо відразу
                    e.target.style.setProperty('--x', x);
                    e.target.style.setProperty('--y', y);

                    if (this.wireId) await Livewire.find(this.wireId).call('saveElementPosition', this.elId, x, y);
                    this.elId = null;
                },
            }
        }
    </script>

</x-filament-panels::page>

