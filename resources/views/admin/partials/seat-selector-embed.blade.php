@php
    /**
     * Ця в’юшка викликається з ViewField::make(...)->viewData([...])
     * Ми отримуємо тут змінні $json (JSON розкладки) і $key (унікальний ключ).
     */
    $json = is_string($json ?? null) && $json !== '' ? $json : '[]';
    $key  = $key ?? ('seat-selector-'.md5($json));
@endphp

{{-- Вставляємо Livewire-компонент і передаємо готовий JSON як проп --}}
@livewire('seat-selector', ['initialLayoutJson' => $json], key($key))
