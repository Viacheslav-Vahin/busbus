@php $props = $record->properties?->toArray() ?? []; @endphp
<div class="space-y-3">
    <div><b>ID:</b> {{ $record->id }}</div>
    <div><b>Коли:</b> {{ $record->created_at }}</div>
    <div><b>Опис:</b> {{ $record->description }}</div>
    <div class="prose max-w-none">
    <pre class="text-xs bg-gray-50 rounded p-3 overflow-x-auto">
{{ json_encode($props, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}
    </pre>
    </div>
</div>
