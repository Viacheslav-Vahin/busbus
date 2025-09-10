{{-- resources/views/filament/pages/channel-sales-report.blade.php --}}
<x-filament-panels::page>
    {{-- Параметри --}}
    <x-filament::section heading="Параметри">
        <x-filament-panels::form wire:submit="makeReport">
            {{ $this->form }}

            <div class="mt-4">
                <x-filament::button type="submit" color="primary">
                    Згенерувати
                </x-filament::button>
            </div>
        </x-filament-panels::form>
    </x-filament::section>

    {{-- Результати --}}
    @if (!empty($report))

        {{-- Зведення по агентам --}}
        @if($report['agents'] !== null)
            <x-filament::section heading="Зведення по агентам">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                        <tr class="font-semibold">
                            <th class="p-2 text-left">Агент</th>
                            <th class="p-2 text-right">К-сть</th>
                            <th class="p-2 text-right">Продано</th>
                            <th class="p-2 text-right">Повернено</th>
                            <th class="p-2 text-right">Нетто</th>
                            <th class="p-2"></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($report['agents'] as $row)
                            <tr>
                                <td class="p-2">{{ $row['agent_name'] }}</td>
                                <td class="p-2 text-right">{{ $row['count_sold'] }}</td>
                                <td class="p-2 text-right">{{ number_format($row['soldTotal'],2,'.',' ') }}</td>
                                <td class="p-2 text-right">{{ number_format($row['returnedTotal'],2,'.',' ') }}</td>
                                <td class="p-2 text-right font-semibold">{{ number_format($row['net'],2,'.',' ') }}</td>
                                <td class="p-2 text-right">
                                    <x-filament::button size="xs" wire:click="showAgentDetails({{ (int)$row['agent_id'] }})">
                                        Деталі
                                    </x-filament::button>
                                </td>
                            </tr>
                        @empty
                            <tr><td class="p-2 text-gray-500" colspan="6">Немає даних</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

        {{-- Самостійні покупці --}}
        @if($report['direct'] !== null)
            <x-filament::section heading="Самостійні покупці (direct)">
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4 items-center">
                    <div>Кількість: <span class="font-semibold">{{ $report['direct']['count_sold'] }}</span></div>
                    <div>Продано: <span class="font-semibold">{{ number_format($report['direct']['soldTotal'],2,'.',' ') }}</span></div>
                    <div>Повернено: <span class="font-semibold">{{ number_format($report['direct']['returnedTotal'],2,'.',' ') }}</span></div>
                    <div>Нетто: <span class="font-semibold">{{ number_format($report['direct']['net'],2,'.',' ') }}</span></div>
                    <div class="text-right">
                        <x-filament::button size="xs" wire:click="showDirectDetails">
                            Деталі
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>
        @endif
    @endif

    {{-- МОДАЛ ДЕТАЛЕЙ --}}
    <x-filament::modal id="agent-bookings-modal" width="7xl" :heading="$detailTitle">
        <div class="overflow-x-auto">
            @if(!is_null($detailAgentId))
                {{-- АГЕНТСЬКІ продані квитки з розрахунками --}}
                <table class="min-w-full text-xs md:text-sm">
                    <thead>
                    <tr class="font-semibold">
                        <th class="p-2 text-left">№</th>
                        <th class="p-2 text-left">№ квитка</th>
                        <th class="p-2 text-left">Пасажир</th>
                        <th class="p-2 text-left">Напрямок</th>
                        <th class="p-2 text-left">Дата відправлення</th>
                        <th class="p-2 text-right">% Агента</th>
                        <th class="p-2 text-right">Ціна</th>
                        <th class="p-2 text-right">Винагорода АГЕНТА</th>
                        <th class="p-2 text-right">До виплати ПЕРЕВІЗНИКУ</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($detailRows as $r)
                        <tr>
                            <td class="p-2">{{ $loop->iteration }}</td>
                            <td class="p-2">{{ $r['ticket_no'] }}</td>
                            <td class="p-2">{{ $r['passenger'] }}</td>
                            <td class="p-2">{{ $r['direction'] }}</td>
                            <td class="p-2">{{ $r['date'] }}</td>
                            <td class="p-2 text-right">
                                {{ rtrim(rtrim(number_format($r['agent_pct'],2,'.',' '),'0'),'.') }}
                            </td>
                            <td class="p-2 text-right">{{ number_format($r['price'],2,'.',' ') }}</td>
                            <td class="p-2 text-right">{{ number_format($r['agent_fee'],2,'.',' ') }}</td>
                            <td class="p-2 text-right">{{ number_format($r['to_carrier'],2,'.',' ') }}</td>
                        </tr>
                    @empty
                        <tr><td class="p-2 text-gray-500" colspan="9">Немає проданих квитків</td></tr>
                    @endforelse
                    </tbody>
                </table>
            @else
                {{-- DIRECT режим – базова таблиця --}}
                <table class="min-w-full text-xs md:text-sm">
                    <thead>
                    <tr class="font-semibold">
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
                            <td class="p-2">{{ $r['ticket_no'] }}</td>
                            <td class="p-2">{{ $r['passenger'] }}</td>
                            <td class="p-2">{{ $r['direction'] }}</td>
                            <td class="p-2">{{ $r['date'] }}</td>
                            <td class="p-2 text-right">{{ number_format($r['price'],2,'.',' ') }}</td>
                            <td class="p-2">{{ $r['status'] ?? '' }}</td>
                            <td class="p-2">{{ $r['payment_method'] ?? '' }}</td>
                        </tr>
                    @empty
                        <tr><td class="p-2 text-gray-500" colspan="7">Немає бронювань</td></tr>
                    @endforelse
                    </tbody>
                </table>
            @endif
        </div>

        <x-slot name="footer">
            <x-filament::button color="success" wire:click="exportAgentDetailsXlsx" :disabled="empty($detailRows)">
                Експорт деталей (XLSX)
            </x-filament::button>
            <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'agent-bookings-modal' })">
                Закрити
            </x-filament::button>
        </x-slot>
    </x-filament::modal>
</x-filament-panels::page>
