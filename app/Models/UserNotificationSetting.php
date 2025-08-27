<?php
// app/Models/UserNotificationSetting.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserNotificationSetting extends Model
{
    protected $fillable = ['user_id','email_enabled','sms_enabled','viber_enabled','telegram_enabled','lang'];
    public function user(){ return $this->belongsTo(User::class); }
}
