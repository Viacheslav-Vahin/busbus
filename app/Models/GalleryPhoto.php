<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GalleryPhoto extends Model
{
    protected $table = 'gallery_photos';

    protected $fillable = [
        'path','w','h','title','tags','placeholder','is_published','position',
    ];

    protected $casts = [
        'tags' => 'array',
        'is_published' => 'boolean',
    ];

    // гарантуємо масив навіть коли в БД null
    public function getTagsAttribute($value)
    {
        return $value ?: [];
    }
}


