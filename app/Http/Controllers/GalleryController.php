<?php

namespace App\Http\Controllers;

use App\Models\GalleryPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GalleryController extends Controller
{
    // GET /api/gallery?tag=&cursor=&limit=&sort=newest|oldest
    public function index(Request $r)
    {
        $limit  = min(48, max(6, (int) $r->get('limit', 24)));
        $tag    = $r->get('tag');
        $cursor = $r->get('cursor');
        $sort   = $r->get('sort', 'newest'); // newest|oldest

        $q = GalleryPhoto::query()->where('is_published', true);

        // Сортуємо по id, щоб узгодити з курсором
        if ($sort === 'oldest') {
            $q->orderBy('id', 'asc');
        } else {
            $q->orderBy('id', 'desc');
        }

        if ($tag) {
            $q->whereJsonContains('tags', $tag);
        }

        if ($cursor) {
            $op = $sort === 'oldest' ? '>' : '<';
            $q->where('id', $op, (int) $cursor);
        }

        $items = $q->limit($limit)->get();

        $next = $items->count() === $limit ? (string) $items->last()->id : null;

        // Теги
        $cloud = GalleryPhoto::query()
            ->where('is_published', true)
            ->pluck('tags')
            ->flatMap(fn ($v) => is_array($v) ? $v : [])
            ->countBy()
            ->map(fn ($count, $t) => ['tag' => $t, 'count' => $count])
            ->values()
            ->sortByDesc('count')
            ->take(30)
            ->values();

        return response()->json([
            'items' => $items->map(function ($p) {
                return [
                    'id'         => $p->id,
                    'url'        => method_exists($p, 'getUrlAttribute') ? $p->url : Storage::url($p->path),
                    'w'          => (int) $p->w,
                    'h'          => (int) $p->h,
                    'title'      => $p->title,
                    'tags'       => $p->tags ?? [],
                    'created_at' => optional($p->created_at)->toIso8601String(),
                    'placeholder'=> $p->placeholder ?? null, // буде null, якщо колонки немає
                ];
            }),
            'nextCursor' => $next,
            'tagsCloud'  => $cloud,
        ]);
    }

    // POST /api/admin/gallery (multipart)
    public function store(Request $r)
    {
        $r->validate([
            'image' => 'required|image|max:12288', // 12MB
            'title' => 'nullable|string|max:255',
            'tags'  => 'nullable', // приймемо і рядок, і масив
        ]);

        $file = $r->file('image');
        $path = $file->store('gallery', 'public');

        [$w, $h] = getimagesize($file->getRealPath());

        // Спроба згенерувати tiny placeholder (якщо є GD)
        $placeholder = null;
        try {
            if (function_exists('imagecreatefromstring')) {
                $img    = imagecreatefromstring(file_get_contents($file->getRealPath()));
                $smallW = 16;
                $smallH = max(1, (int) round($h * ($smallW / max(1, $w))));
                $small  = imagescale($img, $smallW, $smallH);
                ob_start();
                imagejpeg($small, null, 30);
                $placeholder = 'data:image/jpeg;base64,' . base64_encode(ob_get_clean());
                imagedestroy($small);
                imagedestroy($img);
            }
        } catch (\Throwable $e) {
            $placeholder = null; // тихо ідемо далі
        }

        // Підтримати і "tag1, tag2" і ["tag1","tag2"]
        $tagsInput = $r->input('tags', []);
        $tags = is_string($tagsInput)
            ? collect(explode(',', $tagsInput))
                ->map(fn ($t) => trim($t))
                ->filter()
                ->unique()
                ->values()
                ->all()
            : (is_array($tagsInput) ? array_values(array_unique(array_filter($tagsInput))) : []);

        $photo = GalleryPhoto::create([
            'path'         => $path,
            'w'            => $w,
            'h'            => $h,
            'title'        => $r->string('title')->toString(),
            'tags'         => $tags,
            // якщо у таблиці немає цієї колонки — просто приберіть цю лінію
            'placeholder'  => $placeholder,
            'is_published' => true,
        ]);

        return response()->json([
            'id'  => $photo->id,
            'url' => Storage::url($photo->path),
        ], 201);
    }

    public function update(Request $r, GalleryPhoto $photo)
    {
        $r->validate([
            'title' => 'nullable|string|max:255',
            'tags'  => 'nullable|array',
            'tags.*'=> 'string',
        ]);

        $photo->update([
            'title' => $r->input('title', $photo->title),
            'tags'  => $r->input('tags', $photo->tags ?? []),
        ]);

        return response()->json([
            'id'    => $photo->id,
            'url'   => Storage::url($photo->path),
            'title' => $photo->title,
            'tags'  => $photo->tags ?? [],
        ]);
    }

    public function destroy(GalleryPhoto $photo)
    {
        Storage::disk('public')->delete($photo->path);
        $photo->delete();

        return response()->noContent();
    }
}
