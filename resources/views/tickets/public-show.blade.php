{{-- resources/views/tickets/public-show.blade.php --}}
    <!doctype html>
<meta charset="utf-8">

<h3>Квиток {{ $b->ticket_serial }}</h3>
<p>
    Маршрут: {{ $b->route_display }} •
    Дата: {{ \Carbon\Carbon::parse($b->date)->format('d.m.Y') }} •
    Місце: №{{ $b->selected_seat }}
</p>
<p>Статус: {{ $b->checked_in_at ? 'Використаний' : 'Дійсний' }}</p>

@if($b->ticket_pdf_path)
    <a href="{{ Storage::url($b->ticket_pdf_path) }}" target="_blank">
        Завантажити PDF
    </a>
@endif
