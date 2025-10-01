<?php
// app/Models/CmsSetting.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Support\CmsCache;
use Illuminate\Database\Eloquent\Casts\Attribute;

class CmsSetting extends Model {
    protected $fillable = ['group','key','value'];
//    protected $casts = ['value'=>'array'];

    protected function value(): Attribute
    {
        return Attribute::make(
            get: function ($v) {
                if ($this->is_json) {
                    return is_array($v) ? $v : (json_decode($v, true) ?? []);
                }
                // звичайний текст
                return is_string($v) ? $v : (is_null($v) ? '' : (string) $v);
            },
            set: function ($v) {
                if ($this->is_json) {
                    return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                return (string) $v;
            }
        );
    }
    protected static function booted(): void
    {
        static::saved(function (self $m) {
            $key = "cms:page:{$m->slug}";
            CmsCache::repo('cms')->forget($key);
        });

        static::deleted(function (self $m) {
            $key = "cms:page:{$m->slug}";
            CmsCache::repo('cms')->forget($key);
        });
    }

// приклад отримання з кешу
    public static function bySlug(string $slug): ?self
    {
        $key = "cms:page:{$slug}";
        return CmsCache::repo('cms')->rememberForever($key, fn () =>
        static::where('slug', $slug)->first()
        );
    }
}
