<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleSearchConsoleConnection extends Model
{
    protected $fillable = ['access_token', 'refresh_token', 'expires_at', 'scopes', 'property', 'sitemap_status', 'last_sitemap_hash', 'performance_data', 'last_sitemap_submission_at', 'last_google_refresh_at'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
            'scopes' => 'array',
            'sitemap_status' => 'array',
            'performance_data' => 'array',
            'last_sitemap_submission_at' => 'datetime',
            'last_google_refresh_at' => 'datetime',
        ];
    }
}
