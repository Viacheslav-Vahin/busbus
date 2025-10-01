<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstagramFeedController extends Controller
{
    public function index(Request $r)
    {
        $limit = (int)($r->get('limit', config('services.instagram.limit', env('INSTAGRAM_FEED_LIMIT', 12))));
        $limit = max(3, min(24, $limit));

        $token = env('INSTAGRAM_ACCESS_TOKEN');
        if (!$token) {
            return response()->json(['items' => []]);
        }

        // Кеш на 15 хв
        $data = Cache::remember("ig-feed:$limit", 60 * 15, function () use ($token, $limit) {
            // 1) останні media id
            $resp = Http::get('https://graph.instagram.com/me/media', [
                'fields' => 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp,children{media_type,media_url,thumbnail_url}',
                'access_token' => $token,
                'limit' => $limit,
            ]);
            if (!$resp->ok()) Log::error('IG error', ['status'=>$resp->status(), 'body'=>$resp->body()]);
            if (!$resp->ok()) return ['items' => []];

            $items = collect($resp->json('data', []))
                ->map(function ($m) {
                    $type = $m['media_type'] ?? 'IMAGE';
                    $isVideo = $type === 'VIDEO';
                    $isCarousel = $type === 'CAROUSEL_ALBUM';

                    // Для каруселі беремо перше дитя як прев’ю (або сам media_url)
                    $preview = $m['media_url'] ?? null;
                    if ($isCarousel && isset($m['children']['data'][0])) {
                        $child = $m['children']['data'][0];
                        $preview = $child['media_url'] ?? ($child['thumbnail_url'] ?? $preview);
                    }
                    if ($isVideo) {
                        // у VIDEO може бути thumbnail_url
                        $preview = $m['thumbnail_url'] ?? $preview;
                    }

                    return [
                        'id'        => $m['id'],
                        'type'      => $type, // IMAGE | VIDEO | CAROUSEL_ALBUM
                        'preview'   => $preview,
                        'media_url' => $m['media_url'] ?? null,
                        'permalink' => $m['permalink'] ?? '#',
                        'caption'   => $m['caption'] ?? null,
                        'timestamp' => $m['timestamp'] ?? null,
                    ];
                })
                ->filter(fn($x) => !empty($x['preview']))
                ->values()
                ->all();

            return ['items' => $items];
        });

        return response()->json($data);
    }
}
