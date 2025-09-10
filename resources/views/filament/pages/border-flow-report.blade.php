<x-filament-panels::page>
    <x-filament::section heading="Параметри">
        <x-filament-panels::form wire:submit="makeReport">
            {{ $this->form }}
            <div class="mt-4">
                <x-filament::button type="submit" color="primary">Згенерувати</x-filament::button>
            </div>
        </x-filament-panels::form>
    </x-filament::section>

    @if(!empty($report))
        <x-filament::section heading="Зведення">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="p-4 rounded-lg bg-black/10">
                    <div class="text-lg font-semibold mb-2">Виїзд з України</div>
                    <div class="flex items-center gap-6">
                        <div>К-сть: <span class="font-bold">{{ $report['summary']['exit']['count'] }}</span></div>
                        <div>Сума: <span class="font-bold">{{ number_format($report['summary']['exit']['sum'],2,'.',' ') }}</span></div>
                        <div class="ml-auto">
                            <x-filament::button size="xs" wire:click="showDetails('exit')">Деталі</x-filament::button>
                        </div>
                    </div>
                    @if(!empty($report['summary']['exit']['daily']))
                        <div class="mt-3 overflow-x-auto">
                            <table class="min-w-full text-xs">
                                <thead><tr><th class="p-1">Дата</th><th class="p-1 text-right">К-сть</th><th class="p-1 text-right">Сума</th></tr></thead>
                                <tbody>
                                @foreach($report['summary']['exit']['daily'] as $d=>$r)
                                    <tr>
                                        <td class="p-1">{{ \Illuminate\Support\Carbon::parse($d)->format('d.m.Y') }}</td>
                                        <td class="p-1 text-right">{{ $r['count'] }}</td>
                                        <td class="p-1 text-right">{{ number_format($r['sum'],2,'.',' ') }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <div class="p-4 rounded-lg bg-black/10">
                    <div class="text-lg font-semibold mb-2">В'їзд в Україну</div>
                    <div class="flex items-center gap-6">
                        <div>К-сть: <span class="font-bold">{{ $report['summary']['entry']['count'] }}</span></div>
                        <div>Сума: <span class="font-bold">{{ number_format($report['summary']['entry']['sum'],2,'.',' ') }}</span></div>
                        <div class="ml-auto">
                            <x-filament::button size="xs" wire:click="showDetails('entry')">Деталі</x-filament::button>
                        </div>
                    </div>
                    @if(!empty($report['summary']['entry']['daily']))
                        <div class="mt-3 overflow-x-auto">
                            <table class="min-w-full text-xs">
                                <thead><tr><th class="p-1">Дата</th><th class="p-1 text-right">К-сть</th><th class="p-1 text-right">Сума</th></tr></thead>
                                <tbody>
                                @foreach($report['summary']['entry']['daily'] as $d=>$r)
                                    <tr>
                                        <td class="p-1">{{ \Illuminate\Support\Carbon::parse($d)->format('d.m.Y') }}</td>
                                        <td class="p-1 text-right">{{ $r['count'] }}</td>
                                        <td class="p-1 text-right">{{ number_format($r['sum'],2,'.',' ') }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </x-filament::section>
    @endif

    {{-- Модал "деталі" --}}
    <x-filament::modal id="border-flow-details" width="7xl" :heading="($detailType === 'exit' ? 'Деталі: Виїзд з України' : 'Деталі: Вʼїзд в Україну')">
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs md:text-sm">
                <thead>
                <tr class="font-semibold">
                    <th class="p-2 text-left">№</th>
                    <th class="p-2 text-left">№ квитка</th>
                    <th class="p-2 text-left">Пасажир</th>
                    <th class="p-2 text-left">Напрямок</th>
                    <th class="p-2 text-left">Дата</th>
                    <th class="p-2 text-right">Ціна</th>
                    <th class="p-2 text-left">Статус</th>
                    <th class="p-2 text-left">Спосіб оплати</th>
                </tr>
                </thead>
                <tbody>
                @forelse($detailRows as $r)
                    <tr>
                        <td class="p-2">{{ $loop->iteration }}</td>
                        <td class="p-2">{{ $r['ticket_no'] }}</td>
                        <td class="p-2">{{ $r['passenger'] }}</td>
                        <td class="p-2">{{ $r['direction'] }}</td>
                        <td class="p-2">{{ \Illuminate\Support\Carbon::parse($r['date'])->format('d.m.Y') }}</td>
                        <td class="p-2 text-right">{{ number_format($r['price'],2,'.',' ') }}</td>
                        <td class="p-2">{{ $r['status'] }}</td>
                        <td class="p-2">{{ $r['payment_method'] }}</td>
                    </tr>
                @empty
                    <tr><td class="p-2 text-gray-500" colspan="8">Немає записів</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <x-slot name="footer">
            <x-filament::button color="success" wire:click="$wire.exportXlsx()">Експорт XLSX</x-filament::button>
            <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'border-flow-details' })">Закрити</x-filament::button>
        </x-slot>
    </x-filament::modal>
</x-filament-panels::page>
