<?php
// app/Models/GlobalAccount.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GlobalAccount extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'details'];
}
