<?php
// app/Models/BusLayoutElement.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusLayoutElement extends Model {
    protected $fillable = ['bus_id','type','x','y','w','h','label','meta'];
    protected $casts = ['meta'=>'array'];
    public function bus(){ return $this->belongsTo(Bus::class); }
}
