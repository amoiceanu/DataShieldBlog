<?php

namespace App\Services\Exposure;

use Illuminate\Http\Request;

class Privacy
{
    public function digest(string $value): string
    {
        abort_unless(config('exposure.hmac_key'), 503, 'Verificarea este momentan indisponibilă.');

        return hash_hmac('sha256', $value, config('exposure.hmac_key'));
    }

    public function owner(Request $request): string
    {
        if (! $request->session()->has('exposure_owner')) {
            $request->session()->put('exposure_owner', bin2hex(random_bytes(32)));
        }

        return $this->digest('session:'.$request->session()->get('exposure_owner'));
    }

    public function address(string $email): string
    {
        return $this->digest('email:'.mb_strtolower(trim($email)));
    }

    public static function text(mixed $value, int $limit = 4000): string
    {
        return mb_substr(html_entity_decode(strip_tags(is_string($value) ? $value : ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 0, $limit);
    }

    public static function url(mixed $url): ?string
    {
        if (! is_string($url) || strlen($url) > 2048 || ! filter_var($url, FILTER_VALIDATE_URL) || preg_match('/[\x00-\x20]/', $url)) {
            return null;
        }
        $p = parse_url($url);

        return in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true) && ! isset($p['user']) && ! isset($p['pass']) ? $url : null;
    }
}
