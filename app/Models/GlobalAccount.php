<?php
// app/Models/GlobalAccount.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class GlobalAccount extends Model
{
    protected $fillable = ['title','details','email_whitelist'];

    public function isVisibleTo(?User $user): bool
    {
        if (!$user) return false;
        $raw = (string)($this->email_whitelist ?? '');
        $emails = array_filter(array_map(
            'strtolower',
            array_map('trim', explode(',', $raw))
        ));
        // Порожньо — рахунок доступний усім
        if (empty($emails)) return true;
        return in_array(strtolower($user->email), $emails, true);
    }
}
