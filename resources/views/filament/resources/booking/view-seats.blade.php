@extends('filament::page')

@section('content')
    <div class="container">
        <h2>{{ $this->getHeading() }}</h2>

        <!-- Display seat layout -->
        <div class="bus-seat-layout">
            @foreach($bus->seat_layout as $seat)
                <button class="seat {{ $seat['type'] }}" data-seat-number="{{ $seat['number'] }}">
                    {{ $seat['number'] }}
                </button>
            @endforeach
        </div>

        <!-- Form to reserve seat -->
        <div id="reserve-seat-form" class="mt-4" style="display: none;">
            <h4>Бронювання місця</h4>
            <form action="{{ route('booking.reserveSeat') }}" method="POST">
                @csrf
                <input type="hidden" name="bus_id" value="{{ $bus->id }}">
                <input type="hidden" id="seat_number" data-seatnumber="{{ $bus->id }}" name="seat_number">
                <div class="form-group">
                    <label for="passenger_name">Ім'я пасажира:</label>
                    <input type="text" id="passenger_name" name="passenger_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="passenger_surname">Прізвище пасажира:</label>
                    <input type="text" id="passenger_surname" name="passenger_surname" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="phone_number">Номер телефону:</label>
                    <input type="text" id="phone_number" name="phone_number" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="email">Електронна пошта:</label>
                    <input type="email" id="email" name="email" class="form-control">
                </div>
                <div class="form-group">
                    <label for="note">Примітка:</label>
                    <input type="text" id="note" name="note" class="form-control">
                </div>
                <button type="submit" class="btn btn-success mt-3">Забронювати місце</button>
            </form>
        </div>
    </div>

    <script>
        document.querySelectorAll('.seat').forEach(function (seatButton) {
            seatButton.addEventListener('click', function () {
                document.getElementById('seat_number').value = this.dataset.seatNumber;
                document.getElementById('reserve-seat-form').style.display = 'block';
            });
        });
    </script>
@endsection
