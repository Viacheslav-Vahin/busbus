<?php
// app/Models/Photo.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Photo extends Model {
    protected $fillable = ['path','w','h','title','tags','placeholder'];
    protected $casts = ['tags' => 'array'];
    protected $appends = ['url'];
    public function getUrlAttribute() { return \Storage::disk('public')->url($this->path); }
}
