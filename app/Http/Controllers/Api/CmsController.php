<?php
// app/Http/Controllers/Api/CmsController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CmsMenu;
use App\Models\CmsPage;
use App\Models\CmsSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CmsController extends Controller
{
    public function page(Request $r, string $slug) {
        $locale = $r->query('locale', app()->getLocale());
        $data = Cache::tags('cms')->remember("page:$slug:$locale", 600, function () use ($slug) {
            $page = CmsPage::where('slug',$slug)->where('status','published')->firstOrFail();
            return [
                'slug'   => $page->slug,
                'title'  => $page->title[$this->locale()] ?? ($page->title['uk'] ?? ''),
                'blocks' => $page->blocks ?? [],
                'meta'   => $page->meta ?? [],
            ];
        });
        return response()->json($data);
    }

    public function menu(string $key) {
        $menu = Cache::tags('cms')->remember("menu:$key", 600, fn() =>
        CmsMenu::where('key',$key)->firstOrFail()
        );
        return response()->json(['key'=>$menu->key,'items'=>$menu->items]);
    }

    public function settings(Request $r) {
        $keys = (array) $r->query('keys', []);
        $all  = Cache::tags('cms')->remember("settings", 600, fn() =>
        CmsSetting::query()->get(['key','value'])->pluck('value','key')
        );
        $data = $keys ? collect($all)->only($keys) : $all;
        return response()->json($data);
    }

    private function locale(): string {
        return app()->getLocale() ?: 'uk';
    }
}
