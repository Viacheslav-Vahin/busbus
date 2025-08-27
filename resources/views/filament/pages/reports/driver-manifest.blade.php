<x-filament::page>
    @once
        @push('styles')
            <style>
                /* Компактні поля лише для цієї форми */
                .driver-manifest-compact .fi-field-wrp { gap: .25rem; }
                .driver-manifest-compact .fi-input,
                .driver-manifest-compact .fi-select,
                .driver-manifest-compact .fi-date-time-picker { font-size: .875rem; } /* text-sm */
                .driver-manifest-compact .fi-input input,
                .driver-manifest-compact .fi-select select,
                .driver-manifest-compact .fi-date-time-picker input {
                    padding-top: .375rem;   /* ~py-1.5 */
                    padding-bottom: .375rem;
                }
                .driver-manifest-compact .fi-fo-field { margin-top: .25rem; margin-bottom: .25rem; }
            </style>
        @endpush
    @endonce
    {{ $this->form }}

    <x-filament::button
        wire:click="generate"
        class="mt-4"
    >
        Згенерувати PDF
    </x-filament::button>
</x-filament::page>
