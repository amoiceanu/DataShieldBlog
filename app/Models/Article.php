<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Article extends Model
{
    protected $fillable = ['category_id', 'title', 'slug', 'excerpt', 'content', 'image', 'author', 'published_at', 'reading_time', 'is_featured', 'status'];

    protected function casts(): array
    {
        return ['published_at' => 'date', 'is_featured' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
