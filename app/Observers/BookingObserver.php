<?php

namespace App\Observers;

use App\Models\Booking;

class BookingObserver
{
    public function creating(Booking $b): void
    {
        if (!app()->bound('auth') || !auth()->check()) return;

        $user = auth()->user();
        // якщо є Spatie:
        $isStaff = method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole(['admin','manager'])
            : in_array($user->role ?? null, ['admin','manager']);

        if ($isStaff && empty($b->agent_id)) {
            $b->agent_id = $user->id;
        }
    }
}
