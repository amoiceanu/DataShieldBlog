<?php

namespace App\Services\Exposure;

use Illuminate\Support\Facades\Http;

class PublicSearch
{
    public function query(string $subject, string $location = ''): string
    {
        // Quoted literals only. User-controlled operators, quotes and control characters are discarded.
        $clean = fn ($v) => trim(preg_replace('/[^\p{L}\p{M}\p{N}\s@.+\-_\x{2019}\x{0027}]/u', ' ', $v));
        $exactEmail = filter_var($subject, FILTER_VALIDATE_EMAIL) && ! str_contains($subject, '"') && ! str_contains($subject, '\\');

        return '"'.($exactEmail ? $subject : $clean($subject)).'"'.($location !== '' ? ' "'.$clean($location).'"' : '');
    }

    public function search(string $subject, string $location, int $limit): ProviderResult
    {
        if (! app(Settings::class)->configured('mentions')) {
            return new ProviderResult('unavailable');
        }
        try {
            $r = Http::withHeaders(['X-Subscription-Token' => config('exposure.brave_key')])->acceptJson()->connectTimeout(5)->timeout(15)->withOptions(['allow_redirects' => false, 'debug' => false])->get('https://api.search.brave.com/res/v1/web/search', ['q' => $this->query($subject, $location), 'count' => min(20, $limit), 'text_decorations' => false, 'spellcheck' => false, 'result_filter' => 'web', 'safesearch' => 'moderate']);
            if ($r->status() === 429) {
                return new ProviderResult('quota', [], min(300, max(2, (int) $r->header('Retry-After'))));
            }
            if (! $r->successful()) {
                return new ProviderResult('unavailable');
            }
            $data = $r->json();
            if (! is_array($data) || ($data['type'] ?? null) !== 'search' || (isset($data['web']) && ! is_array($data['web']['results'] ?? null))) {
                return new ProviderResult('incomplete');
            }
            $items = [];
            $invalid = false;
            foreach (array_slice($data['web']['results'] ?? [], 0, $limit) as $item) {
                if (! is_array($item)) {
                    $invalid = true;

                    continue;
                }
                $url = Privacy::url($item['url'] ?? null);
                if (! $url) {
                    $invalid = true;

                    continue;
                }
                $items[] = ['title' => Privacy::text($item['title'] ?? '', 300), 'url' => $url, 'domain' => parse_url($url, PHP_URL_HOST), 'description' => Privacy::text($item['description'] ?? '', 1000), 'classification' => null];
            }

            return new ProviderResult($invalid ? 'incomplete' : 'complete', $items);
        } catch (\Throwable) {
            return new ProviderResult('unavailable');
        }
    }
}
