<x-filament::page>
    <div class="space-y-4">
        {{ $this->form }}
        @php($totals = $this->getTotals())
        <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="rounded-xl bg-gray-900/30 p-4">
                <div class="text-sm opacity-70">Кількість квитків</div>
                <div class="text-2xl font-semibold">{{ $totals['count'] }}</div>
            </div>
            <div class="rounded-xl bg-gray-900/30 p-4">
                <div class="text-sm opacity-70">Сума (грн)</div>
                <div class="text-2xl font-semibold">{{ number_format($totals['sum'],2,',',' ') }}</div>
            </div>
            <div class="rounded-xl bg-gray-900/30 p-4">
                <div class="text-sm opacity-70">Період</div>
                <div class="text-2xl font-semibold">
                    {{ $from_date ?? '—' }} — {{ $to_date ?? '—' }}
                </div>
            </div>
        </div>
        {{ $this->table }}
    </div>

</x-filament::page>
