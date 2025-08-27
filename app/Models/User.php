<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasRoles;

    // Явно вкажемо guard, якщо у проєкті лише 'web'
    protected $guard_name = 'web';

    protected $fillable = [
        'name',
        'surname',     // якщо є
        'email',
        'phone',       // якщо є
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Доступ до панелей Filament.
     * Якщо у тебе одна адмін-панель — лиши гілку 'admin'.
     * Якщо зробиш окрему панель для водія — додай 'driver'.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin'  => $this->hasAnyRole(['admin','manager','accountant']) || $this->can('access_admin'),
            'driver' => $this->hasRole('driver'),
            default  => false,
        };
    }
}
