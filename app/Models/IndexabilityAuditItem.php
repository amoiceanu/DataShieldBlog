<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndexabilityAuditItem extends Model
{
    protected $fillable = ['audit_id', 'title', 'url', 'type', 'http_status', 'canonical_url', 'sitemap_included', 'discovered', 'local_status', 'google_status', 'problems', 'google_result', 'content_updated_at'];

    protected function casts(): array
    {
        return ['sitemap_included' => 'boolean', 'discovered' => 'boolean', 'problems' => 'array', 'google_result' => 'array', 'content_updated_at' => 'datetime'];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(IndexabilityAudit::class, 'audit_id');
    }
}
