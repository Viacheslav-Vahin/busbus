@extends('layouts.admin')

@section('content')
    <div x-data="bookingApp()" x-init="init">
{{--        @if($routes->isEmpty())--}}
{{--            <p>No routes found!</p>--}}
{{--        @else--}}
{{--            <p>{{$routes}}}Routes loaded!</p>--}}
{{--        @endif--}}
            <select x-model="selectedRoute">
                <option value="">Виберіть маршрут</option>
                @foreach ($routes as $route)
                    <option value="{{ $route['id'] }}">{{ $route['name'] }}</option>
{{--                    <option value="{{ $route }}">{{ $route }}</option>--}}
                @endforeach
            </select>


            <input type="date" x-model="selectedDate" x-on:change="fetchBuses()">

        <button x-on:click="fetchBuses()">Пошук</button>

        <template x-if="buses.length">
            <div>
                <ul>
                    <template x-for="bus in buses">
                        <li x-text="`Автобус: ${bus.name}, Місць: ${bus.seats_count}`"></li>
                    </template>
                </ul>
            </div>
        </template>
    </div>

    <script>
        function bookingApp() {
            return {
                selectedRoute: '',
                selectedDate: '',
                buses: [],
                routes: @json($routes), // Pass the routes from Blade to Alpine.js

                init() {
                    // Initialization code if needed
                },

                fetchBuses() {
                    fetch(`/get-buses-by-route-date`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            route_id: this.selectedRoute,
                            date: this.selectedDate
                        })
                    })
                        .then(response => response.json())
                        .then(data => {
                            this.buses = data;
                        })
                        .catch(error => console.error('Error:', error));
                }
            };
        }
    </script>

@endsection
