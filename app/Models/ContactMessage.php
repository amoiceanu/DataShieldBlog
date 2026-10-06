<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $fillable = ['name', 'email', 'subject', 'message', 'privacy_accepted_at'];

    protected function casts(): array
    {
        return ['privacy_accepted_at' => 'datetime'];
    }
}
