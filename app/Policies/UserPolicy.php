<?php
namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function accessFilament(User $user): bool
    {
        // ПУСКАТИ ВСІХ (для тесту)
        return true;

        // Або умова, наприклад:
        // return $user->email === 'admin@maxbus.com';
        // або $user->is_admin
    }
}
