<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use Illuminate\Http\Request;

class CmsPageController extends Controller
{
    public function show(string $slug)
    {
        $page = CmsPage::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        $locale = app()->getLocale() ?: 'uk';

        return response()->json([
            'slug'   => $page->slug,
            'title'  => is_array($page->title)
                ? ($page->title[$locale] ?? $page->title['uk'] ?? reset($page->title))
                : $page->title,
            'blocks' => $page->blocks ?? [],
            'meta'   => $page->meta ?? [],
            'published_at' => $page->published_at,
        ]);
    }
}
