<x-filament::page>
    <x-filament::section heading="Параметри звіту" class="mb-6">
        <x-filament-panels::form wire:submit="makeReport">
            {{ $this->form }}
            <div class="mt-4">
                <x-filament::button type="submit" color="primary">Згенерувати</x-filiment::button>
            </div>
        </x-filament-panels::form>
    </x-filament::section>
    @if (!empty($report))
        <div class="space-y-6">
            <div class="text-lg font-bold">
                Звіт з проданих квиткiв за період {{ $report['filters']['from'] }}-{{ $report['filters']['to'] }}
            </div>
            <div>Агентський договір №{{ $report['meta']['contract_no'] }} від {{ $report['meta']['contract_date'] }}</div>
            <div>АГЕНТ: {{ $report['meta']['agent_name'] }}</div>
            <div>ПЕРЕВІЗНИК: {{ $report['meta']['carrier_name'] }}</div>

            <x-filament::section heading="Всього по квитках:">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                        <tr class="font-semibold">
                            <th class="p-2 text-left">Продано ({{ $report['filters']['currency'] }})</th>
                            <th class="p-2 text-left">Повернено</th>
                            <th class="p-2 text-left">Утриманий при поверненні</th>
                            <th class="p-2 text-left">Підсумкова сума</th>
                            <th class="p-2 text-left">Винагорода АГЕНТА</th>
                            <th class="p-2 text-left">До виплати ПЕРЕВІЗНИКУ</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr>
                            <td class="p-2">{{ number_format($report['totals']['soldTotal'],2,'.',' ') }}</td>
                            <td class="p-2">{{ number_format($report['totals']['returnedTotal'],2,'.',' ') }}</td>
                            <td class="p-2">{{ number_format($report['totals']['retainedTotal'],2,'.',' ') }}</td>
                            <td class="p-2 font-semibold">{{ number_format($report['totals']['subtotal'],2,'.',' ') }}</td>
                            <td class="p-2">{{ number_format($report['totals']['agentReward'],2,'.',' ') }}</td>
                            <td class="p-2">{{ number_format($report['totals']['toCarrier'],2,'.',' ') }}</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

            <x-filament::section heading="Продані квитки ({{ $report['filters']['currency'] }})">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                        <tr class="font-semibold">
                            <th class="p-2 text-left">№</th>
                            <th class="p-2 text-left">№ квитка</th>
                            <th class="p-2 text-left">Пасажир</th>
                            <th class="p-2 text-left">Напрямок</th>
                            <th class="p-2 text-left">Дата відправлення</th>
                            <th class="p-2 text-left">Відсоток Агента</th>
                            <th class="p-2 text-left">Ціна</th>
                            <th class="p-2 text-left">Винагорода АГЕНТА</th>
                            <th class="p-2 text-left">До виплати ПЕРЕВІЗНИКУ</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($report['sold'] as $i => $row)
                            <tr>
                                <td class="p-2">{{ $i+1 }}</td>
                                <td class="p-2">{{ $row['ticket_no'] }}</td>
                                <td class="p-2">{{ $row['passenger'] }}</td>
                                <td class="p-2">{{ $row['direction'] }}</td>
                                <td class="p-2">{{ $row['date'] }}</td>
                                <td class="p-2">{{ rtrim(rtrim(number_format($row['agent_pct'], 2, '.', ''), '0'), '.') }}</td>
                                <td class="p-2">{{ number_format($row['price'],2,'.',' ') }}</td>
                                <td class="p-2">{{ number_format($row['agent_fee'],2,'.',' ') }}</td>
                                <td class="p-2">{{ number_format($row['to_carrier'],2,'.',' ') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

            <x-filament::section heading="Скасовані квитки в рамках договірної відповідальності АГЕНТА">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                        <tr class="font-semibold">
                            <th class="p-2 text-left">№</th>
                            <th class="p-2 text-left">№ квитка</th>
                            <th class="p-2 text-left">Пасажир</th>
                            <th class="p-2 text-left">Напрямок</th>
                            <th class="p-2 text-left">Дата відправлення</th>
                            <th class="p-2 text-left">Відсоток (утримання)</th>
                            <th class="p-2 text-left">Ціна квитка</th>
                            <th class="p-2 text-left">Винагорода АГЕНТА</th>
                            <th class="p-2 text-left">До виплати ПЕРЕВІЗНИКУ</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($report['canceled'] as $i => $row)
                            <tr>
                                <td class="p-2">{{ $i+1 }}</td>
                                <td class="p-2">{{ $row['ticket_no'] }}</td>
                                <td class="p-2">{{ $row['passenger'] }}</td>
                                <td class="p-2">{{ $row['direction'] }}</td>
                                <td class="p-2">{{ $row['date'] }}</td>
                                <td class="p-2">{{ rtrim(rtrim(number_format($row['retention_pct'], 2, '.', ''), '0'), '.') }}</td>
                                <td class="p-2">{{ number_format($row['price'],2,'.',' ') }}</td>
                                <td class="p-2">{{ number_format($row['agent_from_retention'],2,'.',' ') }}</td>
                                <td class="p-2">{{ number_format($row['carrier_from_retention'],2,'.',' ') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

            <div class="grid grid-cols-2 gap-8 pt-6">
                <div>
                    <div>АГЕНТ: {{ $report['meta']['agent_name'] }}</div>
                    <div class="mt-6">Підпис _____________________</div>
                    <div class="mt-2">ПІБ ________________________</div>
                </div>
                <div>
                    <div>ПЕРЕВІЗНИК: {{ $report['meta']['carrier_name'] }}</div>
                    <div class="mt-6">Підпис _____________________</div>
                    <div class="mt-2">ПІБ ________________________</div>
                </div>
            </div>
        </div>
    @else
        <div class="text-gray-500">Заповни форму зверху і натисни “Згенерувати”.</div>
    @endif
</x-filament::page>
