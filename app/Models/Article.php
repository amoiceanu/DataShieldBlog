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

    public function getCoverIconAttribute(): string
    {
        return match ($this->slug) {
            'primele-30-minute-cont-compromis' => 'bi-stopwatch',
            'cum-elimini-extensii-suspecte-browser' => 'bi-puzzle',
            'cum-verifici-permisiuni-aplicatii-telefon' => 'bi-sliders',
            default => match ($this->category?->slug) {
                'fraude-online', 'stiri-alerte' => 'bi-exclamation-triangle',
                'date-personale', 'confidentialitate' => 'bi-person-lock',
                'parole-autentificare' => 'bi-key',
                'retele-sociale' => 'bi-people',
                'securitate-dispozitive' => 'bi-phone',
                'ghiduri-practice' => 'bi-journal-check',
                'inteligenta-artificiala' => 'bi-cpu',
                'securitate-financiara' => 'bi-credit-card',
                default => 'bi-shield-lock',
            },
        };
    }
}
