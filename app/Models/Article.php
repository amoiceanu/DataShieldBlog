<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Article extends Model
{
    protected $fillable = ['category_id', 'title', 'slug', 'excerpt', 'content', 'image', 'author', 'published_at', 'reading_time', 'is_featured', 'status', 'seo_title', 'meta_description', 'focus_keyword', 'secondary_keywords'];

    protected function casts(): array
    {
        return ['published_at' => 'date', 'is_featured' => 'boolean', 'secondary_keywords' => 'array'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function getImageAltAttribute(): string
    {
        return match ($this->image) {
            'cont-facebook-instagram-spart.jpg' => 'Telefon cu alerte de conectare neobișnuită, lacăt roșu și scut de securitate',
            'telefon-microfon-privacy.jpg' => 'Telefon cu indicator de microfon, semnale de publicitate și scut roșu pentru confidențialitate',
            default => 'Ilustrație pentru '.$this->title,
        };
    }
}
