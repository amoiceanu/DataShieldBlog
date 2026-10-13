<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IndexabilityAudit extends Model
{
    protected $fillable = ['status', 'summary', 'error', 'started_at', 'completed_at'];

    protected function casts(): array
    {
        return ['summary' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(IndexabilityAuditItem::class, 'audit_id');
    }
}
