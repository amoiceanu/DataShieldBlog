<?php

namespace App\Services\Exposure;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Settings
{
    public function all(): array
    {
        $defaults = ['enabled' => (bool) config('exposure.enabled'), 'menu' => true, 'breaches' => true, 'mentions' => true, 'ip_limit' => 10, 'session_limit' => 5, 'address_limit' => 3, 'daily_limit' => 100, 'max_results' => 10, 'retention_hours' => 24, 'provider_interval' => 2];
        if (! Schema::hasTable('exposure_settings')) {
            return $defaults;
        }
        $row = DB::table('exposure_settings')->find(1);

        return array_replace($defaults, $row ? json_decode($row->options, true) : []);
    }

    public function configured(string $module): bool
    {
        if (! config('exposure.hmac_key')) {
            return false;
        }

        return $module === 'breaches' ? (bool) (config('exposure.hibp_key') && config('exposure.hibp_enabled')) : (bool) (config('exposure.brave_key') && config('exposure.brave_enabled'));
    }

    public function mailReady(): bool
    {
        return app()->environment('testing') || ! in_array(config('mail.default'), ['log', 'array', null], true);
    }
}
