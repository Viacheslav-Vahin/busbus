<?php
// app/Models/NotificationLog.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $fillable = ['type','channel','booking_id','order_id','to','status','meta'];
    protected $casts = ['meta'=>'array'];
}
