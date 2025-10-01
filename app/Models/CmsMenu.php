<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Support\CmsCache;

class CmsMenu extends Model {
    protected $fillable = ['key','items'];
    protected $casts = ['items'=>'array'];
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
