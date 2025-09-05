<x-filament-panels::page>
    <style>
        #grid {
            --cols: 24;
            --rows: 8;
            --cell: 52px;
            --seat-scale: .85;
            width: calc(var(--cols) * var(--cell));
            height: calc(var(--rows) * var(--cell));
        }
        .layout-grid { grid-template-columns: repeat(var(--cols), var(--cell)); grid-template-rows: repeat(var(--rows), var(--cell)); }
        .layout-grid > div { border: 1px solid rgba(255, 255, 255, .08); }

        .seat {
            position: absolute;
            width: calc(var(--cell) * var(--seat-scale));
            height: calc(var(--cell) * var(--seat-scale));
            left: calc(var(--cell) * (var(--x) + .5));
            top: calc(var(--cell) * (var(--y) + .5));
            transform: translate(-50%, -50%);
            display: flex; align-items: center; justify-content: center;
            border-radius: .35rem; font-size: 12px; font-weight: 700; line-height: 1;
            background: rgb(245, 128, 11);
            border: 1px solid rgba(255, 255, 255, .25);
            box-shadow: 0 1px 2px rgba(0, 0, 0, .25);
            user-select: none; cursor: grab;
        }

        .el {
            position: absolute;
            left: calc(var(--cell) * var(--x));
            top: calc(var(--cell) * var(--y));
            width: calc(var(--cell) * var(--w));
            height: calc(var(--cell) * var(--h));
            display: flex; align-items: center; justify-content: center;
            background: rgba(245, 158, 11, .25);
            border: 1px solid rgba(245, 158, 11, .55);
            border-radius: .35rem; font-size: 14px;
            user-select: none; cursor: grab;
        }
    </style>

    <div x-data="seatLayout({ colsInit: {{ $this->cols }}, rowsInit: {{ $this->rows }} })" x-init="init()" class="space-y-3">
        <div class="flex items-center justify-between">
            <div class="text-sm text-gray-500">
                Перетягніть — об’єкт «прилипне» до найближчої клітинки при відпусканні.
            </div>
            <div class="flex items-center gap-2">
                <button type="button" class="px-2 py-1 rounded border" x-on:click="zoomOut()">−</button>
                <span class="text-sm tabular-nums" x-text="cell + 'px'"></span>
                <button type="button" class="px-2 py-1 rounded border" x-on:click="zoomIn()">+</button>
                <!-- ✨ ДОДАНО: підігнати -->
                <button type="button" class="px-2 py-1 rounded border" x-on:click="fit()">Підігнати</button>
            </div>
        </div>

        <div class="overflow-auto" x-ref="scrollHost" style="min-height: 420px;">
            <div id="grid"
                 x-ref="grid"
                 class="relative border rounded"
                 x-bind:style="`--cols:${cols};--rows:${rows};--cell:${cell}px;`"
                 x-on:dragover.prevent="trackPointer($event)"
            >
                {{-- Сітка (тепер динамічно з PHP) --}}
                <div class="absolute inset-0 grid layout-grid">
                    @for($y=0; $y < $this->rows; $y++)
                        @for($x=0; $x < $this->cols; $x++)
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
                         x-on:dragend="endDragEl($event)"
                         x-on:contextmenu.prevent="openCtxMenu($event, 'seat', {{ $e['id'] }})"

                    >
                        @php $emoji = ['wc'=>'🚻','coffee'=>'☕','driver'=>'🚍','stuardesa'=>'🧑‍✈️','stairs'=>'🪜','exit'=>'🚪']; @endphp
                        {{ $emoji[$e['type']] ?? strtoupper($e['type']) }}
                    </div>
                @endforeach

                {{-- Сидіння --}}
                @foreach($this->seats as $s)
                    <div class="seat"
                          style="--x:{{ $s['x']??0 }};--y:{{ $s['y']??0 }};"
                          draggable="true"
                          data-id="{{ $s['id'] }}"
                          x-on:dragstart="startDragSeat($event)"
                          x-on:dragend="endDragSeat($event)"
                          x-on:contextmenu.prevent="openCtxMenu($event, 'seat', {{ $e['id'] }})"
                    >
                        {{ $s['number'] }}
                    </div>
                @endforeach

                <!-- ✨ Контекстне меню -->
                <div x-show="ctx.open" x-cloak
                     class="fixed z-50 border rounded shadow bg-white dark:bg-gray-900"
                     x-bind:style="`left:${ctx.x}px;top:${ctx.y}px;`"
                     x-on:click.outside="ctx.open=false"
                     x-on:keydown.escape.window="ctx.open=false"
                >
                    <template x-if="ctx.kind === 'seat'">
                        <div>
                            @foreach($this->seatTypes as $t)
                                <button type="button" class="block w-full text-left px-3 py-2 hover:bg-gray-50"
                                        x-on:click="call('setSeatType', ctx.id, {{ $t['id'] }}); ctx.open=false">
                                    {{ $t['name'] }}
                                </button>
                            @endforeach
                            <button type="button" class="block w-full text-left px-3 py-2 hover:bg-gray-50"
                                    x-on:click="call('setSeatType', ctx.id, null); ctx.open=false">
                                Без типу
                            </button>
                            <div class="h-px bg-gray-200 my-1"></div>
                            <button type="button" class="block w-full text-left px-3 py-2 text-red-600 hover:bg-red-50"
                                    x-on:click="call('deleteSeat', ctx.id); ctx.open=false">
                                Видалити сидіння
                            </button>
                        </div>
                    </template>

                    <template x-if="ctx.kind === 'el'">
                        <div>
                            <button type="button" class="block w-full text-left px-3 py-2 text-red-600 hover:bg-red-50"
                                    x-on:click="call('deleteElement', ctx.id); ctx.open=false">
                                Видалити елемент
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <script>
        function seatLayout({ colsInit = 24, rowsInit = 8 } = {}) {
            return {
                cols: colsInit,
                rows: rowsInit,
                cell: 56,
                minCell: 20,   // ✨ зменшив мінімум, щоб влізало більше
                maxCell: 96,

                wireId: null,
                pointerX: 0,
                pointerY: 0,
                gridRect: null,
                seatId: null,
                elId: null,

                // ✨ універсальне контекстне меню
                ctx: { open: false, x: 0, y: 0, kind: null, id: null },

                init() {
                    const root = this.$root.closest('[wire\\:id]');
                    this.wireId = root ? root.getAttribute('wire:id') : null;
                    this.updateRect();
                    window.addEventListener('resize', () => this.updateRect(), { passive: true });
                    this.$watch('cell', () => this.updateRect());

                    // ✨ після першого рендеру
                    this.$nextTick(() => this.fit());
                },

                zoomIn() { this.cell = Math.min(this.cell + 8, this.maxCell); },
                zoomOut() { this.cell = Math.max(this.cell - 8, this.minCell); },

                // ✨ «Підігнати» — підганяє розмір клітинки під видиму область
                fit() {
                    const host = this.$refs.scrollHost;
                    const pad = 16; // невеликий відступ
                    const wAvail = Math.max(200, host.clientWidth - pad);
                    const hAvail = Math.max(200, host.clientHeight - pad);

                    const cellByW = Math.floor(wAvail / this.cols);
                    const cellByH = Math.floor(hAvail / this.rows);
                    const target = Math.max(this.minCell, Math.min(this.maxCell, Math.min(cellByW, cellByH)));

                    if (isFinite(target) && target > 0) this.cell = target;
                },

                updateRect() { this.gridRect = this.$refs.grid.getBoundingClientRect(); },

                trackPointer(e) { this.pointerX = e.clientX; this.pointerY = e.clientY; },

                call(method, ...args) {
                    const cmp = this.wireId ? Livewire.find(this.wireId) : null;
                    if (cmp) return cmp.call(method, ...args);
                },

                // контекстне меню
                openCtxMenu(e, kind, id) {
                    this.ctx = { open: true, x: e.clientX, y: e.clientY, kind, id: parseInt(id) };
                },

                // drag seats
                startDragSeat(e) { this.seatId = e.target.dataset.id; this.updateRect(); },
                async endDragSeat(e) {
                    if (!this.seatId || !this.gridRect) return;
                    let x = Math.round(((e.clientX - this.gridRect.left) / this.gridRect.width) * (this.cols - 1));
                    let y = Math.round(((e.clientY - this.gridRect.top) / this.gridRect.height) * (this.rows - 1));
                    x = Math.max(0, Math.min(this.cols - 1, x));
                    y = Math.max(0, Math.min(this.rows - 1, y));
                    e.target.style.setProperty('--x', x);
                    e.target.style.setProperty('--y', y);
                    if (this.wireId) await Livewire.find(this.wireId).call('savePosition', this.seatId, x, y);
                    this.seatId = null;
                },

                // drag elements
                startDragEl(e) { this.elId = e.target.dataset.id; this.updateRect(); },
                async endDragEl(e) {
                    if (!this.elId || !this.gridRect) return;
                    let x = Math.round(((e.clientX - this.gridRect.left) / this.gridRect.width) * (this.cols - 1));
                    let y = Math.round(((e.clientY - this.gridRect.top) / this.gridRect.height) * (this.rows - 1));
                    x = Math.max(0, Math.min(this.cols - 1, x));
                    y = Math.max(0, Math.min(this.rows - 1, y));
                    e.target.style.setProperty('--x', x);
                    e.target.style.setProperty('--y', y);
                    if (this.wireId) await Livewire.find(this.wireId).call('saveElementPosition', this.elId, x, y);
                    this.elId = null;
                },
            }
        }
    </script>
</x-filament-panels::page>
