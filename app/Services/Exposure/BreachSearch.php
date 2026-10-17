<?php

namespace App\Services\Exposure;

use Illuminate\Support\Facades\Http;

class BreachSearch
{
    public function search(string $email): ProviderResult
    {
        if (! app(Settings::class)->configured('breaches')) {
            return new ProviderResult('unavailable');
        }
        try {
            $r = Http::withHeaders(['hibp-api-key' => config('exposure.hibp_key'), 'User-Agent' => config('exposure.hibp_agent')])->acceptJson()->connectTimeout(5)->timeout(15)->withOptions(['allow_redirects' => false, 'debug' => false])->get('https://haveibeenpwned.com/api/v3/breachedaccount/'.rawurlencode($email), ['truncateResponse' => 'false']);
            if ($r->status() === 404) {
                return new ProviderResult('complete');
            }
            if ($r->status() === 429) {
                return new ProviderResult('quota', [], min(300, max(2, (int) $r->header('Retry-After'))));
            }
            if (! $r->successful()) {
                return new ProviderResult('unavailable');
            }
            $data = $r->json();
            if (! is_array($data) || ! array_is_list($data)) {
                return new ProviderResult('incomplete');
            }
            $items = [];
            foreach ($data as $b) {
                if (! is_array($b) || ! isset($b['Name']) || ! is_array($b['DataClasses'] ?? null)) {
                    return new ProviderResult('incomplete');
                }
                $flags = [];
                foreach (['IsVerified', 'IsFabricated', 'IsSensitive', 'IsRetired', 'IsSpamList', 'IsMalware', 'IsStealerLog', 'IsSubscriptionFree'] as $key) {
                    if (isset($b[$key])) {
                        if (! is_bool($b[$key])) {
                            return new ProviderResult('incomplete');
                        }
                        $flags[$key] = $b[$key];
                    }
                }
                $items[] = ['title' => Privacy::text($b['Title'] ?? $b['Name'], 200), 'domain' => Privacy::text($b['Domain'] ?? '', 253), 'breach_date' => Privacy::text($b['BreachDate'] ?? '', 30), 'added_date' => Privacy::text($b['AddedDate'] ?? '', 40), 'description' => Privacy::text($b['Description'] ?? ''), 'classes' => array_map(fn ($v) => Privacy::text($v, 100), array_slice($b['DataClasses'], 0, 40)), 'flags' => $flags];
            }

            return new ProviderResult('complete', $items);
        } catch (\Throwable) {
            return new ProviderResult('unavailable');
        }
    }
}
