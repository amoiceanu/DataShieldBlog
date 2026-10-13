<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DailyTip extends Model
{
    protected $fillable = ['title', 'slug', 'body', 'status', 'published_at'];

    protected function casts(): array
    {
        return ['published_at' => 'date'];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereNotNull('published_at')->whereDate('published_at', '<=', today());
    }
}
