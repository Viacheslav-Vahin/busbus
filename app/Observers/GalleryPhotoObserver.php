<?php
namespace App\Observers;

use App\Models\GalleryPhoto;
use Illuminate\Support\Facades\Storage;

class GalleryPhotoObserver
{
    public function saving(GalleryPhoto $photo): void
    {
        if ($photo->isDirty('path') && $photo->path) {
            $full = Storage::disk('public')->path($photo->path);
            if (is_file($full)) {
                [$w,$h] = getimagesize($full) ?: [null,null];
                $photo->w = $w; $photo->h = $h;
            }
        }
    }
}
