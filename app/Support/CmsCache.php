<?php
// app/Support/CmsCache.php
namespace App\Support;

use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;

class CmsCache
{
    /** Повертає репозиторій кешу з тегами (якщо підтримуються) або звичайний. */
    public static function repo(array|string|null $tags = null): Repository
    {
        $repo = Cache::store(); // поточний драйвер
        if ($tags && $repo->supportsTags()) {
            return Cache::tags(is_array($tags) ? $tags : [$tags]);
        }
        return $repo;
    }
}
