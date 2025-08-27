<?php
// app/Policies/BookingPolicy.php
namespace App\Policies;
class BookingPolicy {
    public function view(User $user, Booking $booking) {
        return $booking->user_id === $user->id;
    }
}
