<!-- resources/views/admin/routes/index.blade.php -->

@extends('layouts.admin')

@section('content')
    <h1>Вибір маршруту та дати</h1>

    <!-- Форма для вибору маршруту -->
    <select id="routeSelect">
        @foreach($routes as $route)
            <option value="{{ $route->id }}">{{ $route->start_point }} - {{ $route->end_point }}</option>
        @endforeach
    </select>

    <!-- Поле для вибору дати -->
    <input type="date" id="datePicker">

    <!-- Місце для відображення автобусів -->
    <div id="busList"></div>

@endsection

@section('scripts')
    <script>
        document.getElementById('datePicker').addEventListener('change', function() {
            const routeId = document.getElementById('routeSelect').value;
            const date = this.value;

            fetch('/get-buses-by-date', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    route_id: routeId,
                    date: date
                })
            })
                .then(response => response.json())
                .then(data => {
                    const busList = document.getElementById('busList');
                    busList.innerHTML = '';  // Очищаємо попередній список

                    data.forEach(bus => {
                        const busItem = document.createElement('div');
                        busItem.textContent = `Автобус: ${bus.name}, Місць: ${bus.seats_count}`;
                        busList.appendChild(busItem);
                    });
                });
        });
    </script>
@endsection
