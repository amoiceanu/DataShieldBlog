<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ExposureReport extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $hidden = ['subject', 'results', 'owner', 'address_hmac'];

    protected function casts(): array
    {
        return ['subject' => 'encrypted:array', 'results' => 'encrypted:array', 'expires_at' => 'datetime', 'checked_at' => 'datetime'];
    }
}
