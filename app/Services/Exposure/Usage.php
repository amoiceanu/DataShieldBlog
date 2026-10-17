<?php

namespace App\Services\Exposure;

use Illuminate\Support\Facades\DB;

class Usage
{
    public function count(string $provider, string $column): void
    {
        DB::table('exposure_usage')->insertOrIgnore(['day' => today()->toDateString(), 'provider' => $provider]);
        DB::table('exposure_usage')->where('day', today()->toDateString())->where('provider', $provider)->increment($column);
    }

    public function reserve(string $provider, int $limit): bool
    {
        DB::table('exposure_usage')->insertOrIgnore(['day' => today()->toDateString(), 'provider' => $provider]);

        return (bool) DB::table('exposure_usage')->where('day', today()->toDateString())->where('provider', $provider)->where('api_calls', '<', $limit)->increment('api_calls');
    }
}
