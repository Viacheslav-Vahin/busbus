<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stop extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function buses()
    {
        return $this->belongsToMany(Bus::class, 'bus_stops')
            ->withPivot('type', 'time')
            ->withTimestamps();
    }
}


