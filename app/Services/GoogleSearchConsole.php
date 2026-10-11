<?php

namespace App\Services;

use App\Models\GoogleSearchConsoleConnection as Connection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class GoogleSearchConsole
{
    private const AUTHORIZE = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN = 'https://oauth2.googleapis.com/token';
    private const API = 'https://www.googleapis.com/webmasters/v3';
    private const INSPECTION_API = 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect';
    private const SCOPE = 'https://www.googleapis.com/auth/webmasters';

    public function configured(): bool
    {
        return filled(config('services.google_search_console.client_id'))
            && filled(config('services.google_search_console.client_secret'))
            && filled(config('services.google_search_console.redirect_uri'));
    }

    public function authorizationUrl(string $state): string
    {
        return self::AUTHORIZE.'?'.http_build_query([
            'client_id' => config('services.google_search_console.client_id'),
            'redirect_uri' => config('services.google_search_console.redirect_uri'),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    public function exchangeCode(string $code): Connection
    {
        $response = Http::asForm()->timeout(20)->post(self::TOKEN, [
            'code' => $code,
            'client_id' => config('services.google_search_console.client_id'),
            'client_secret' => config('services.google_search_console.client_secret'),
            'redirect_uri' => config('services.google_search_console.redirect_uri'),
            'grant_type' => 'authorization_code',
        ]);
        if (! $response->successful() || ! $response->json('access_token')) {
            throw new RuntimeException('Google nu a confirmat conexiunea OAuth. Verifică aplicația OAuth și URI-ul de redirect.');
        }

        $tokens = $response->json();
        $connection = Connection::first() ?? new Connection();
        $connection->fill([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? $connection->refresh_token,
            'expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600)),
            'scopes' => isset($tokens['scope']) ? explode(' ', $tokens['scope']) : [self::SCOPE],
            'property' => config('services.google_search_console.property'),
        ])->save();

        return $connection;
    }

    public function accessToken(?Connection $connection = null): string
    {
        $connection ??= Connection::first();
        if (! $connection) throw new RuntimeException('Search Console nu este conectat.');
        if ($connection->expires_at?->isAfter(now()->addMinutes(2))) return $connection->access_token;
        if (! $connection->refresh_token) throw new RuntimeException('Conexiunea Google a expirat. Reconectează Search Console.');

        $response = Http::asForm()->timeout(20)->post(self::TOKEN, [
            'client_id' => config('services.google_search_console.client_id'),
            'client_secret' => config('services.google_search_console.client_secret'),
            'refresh_token' => $connection->refresh_token,
            'grant_type' => 'refresh_token',
        ]);
        if (! $response->successful() || ! $response->json('access_token')) {
            throw new RuntimeException('Google nu a putut reînnoi conexiunea. Reconectează Search Console.');
        }

        $connection->update([
            'access_token' => $response->json('access_token'),
            'expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600)),
        ]);

        return $connection->fresh()->access_token;
    }

    public function siteProperties(): array
    {
        return $this->get('/sites')['siteEntry'] ?? [];
    }

    public function sitemapStatus(Connection $connection): ?array
    {
        $site = rawurlencode($connection->property);
        $data = $this->get("/sites/{$site}/sitemaps");
        $sitemapUrl = config('services.google_search_console.sitemap');
        foreach ($data['sitemap'] ?? [] as $sitemap) {
            if (($sitemap['path'] ?? null) === $sitemapUrl) return $sitemap;
        }

        return null;
    }

    public function submitSitemap(Connection $connection, string $contentHash): void
    {
        $site = rawurlencode($connection->property);
        $sitemap = rawurlencode(config('services.google_search_console.sitemap'));
        $response = Http::withToken($this->accessToken($connection))->timeout(20)->put(self::API."/sites/{$site}/sitemaps/{$sitemap}");
        if (! $response->successful()) throw new RuntimeException('Google Search Console nu a confirmat trimiterea sitemap-ului (HTTP '.$response->status().').');
        $connection->update(['last_sitemap_submission_at' => now(), 'last_sitemap_hash' => $contentHash]);
    }

    public function refreshData(Connection $connection): array
    {
        $token = $this->accessToken($connection);
        $site = rawurlencode($connection->property);
        $sitemapResponse = Http::withToken($token)->timeout(20)->get(self::API."/sites/{$site}/sitemaps");
        if (! $sitemapResponse->successful()) throw new RuntimeException('Google nu a returnat starea sitemap-urilor (HTTP '.$sitemapResponse->status().').');
        $sitemapUrl = config('services.google_search_console.sitemap');
        $sitemap = collect($sitemapResponse->json('sitemap', []))->firstWhere('path', $sitemapUrl);

        $startDate = now()->subDays(28)->toDateString();
        $endDate = now()->subDay()->toDateString();
        $performance = Cache::remember('google-search-console.analytics.'.hash('sha256', $connection->property.'|'.$startDate.'|'.$endDate), now()->addHours(6), function () use ($token, $site, $startDate, $endDate): array {
            $response = Http::withToken($token)->timeout(20)->post(self::API."/sites/{$site}/searchAnalytics/query", [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'rowLimit' => 1,
            ]);
            if (! $response->successful()) throw new RuntimeException('Google nu a returnat datele de performanță (HTTP '.$response->status().').');

            return $response->json('rows.0', ['clicks' => 0, 'impressions' => 0, 'ctr' => 0, 'position' => null]);
        });

        $data = [
            'sitemap' => $sitemap,
            'performance' => $performance,
            'period' => ['from' => $startDate, 'to' => $endDate],
            'refreshed_at' => now()->toIso8601String(),
        ];
        $connection->update(['sitemap_status' => $sitemap, 'performance_data' => $data, 'last_google_refresh_at' => now()]);

        return $data;
    }

    public function inspect(Connection $connection, string $url): array
    {
        $response = Http::withToken($this->accessToken($connection))->timeout(30)->post(self::INSPECTION_API, [
            'inspectionUrl' => $url,
            'siteUrl' => $connection->property,
            'languageCode' => 'ro-RO',
        ]);
        if (! $response->successful()) throw new RuntimeException('Google nu a putut inspecta URL-ul (HTTP '.$response->status().').');

        return $response->json('inspectionResult', []);
    }

    public function revoke(Connection $connection): void
    {
        try {
            Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/revoke', ['token' => $connection->refresh_token ?: $connection->access_token]);
        } finally {
            $connection->delete();
        }
    }

    private function get(string $path): array
    {
        $response = Http::withToken($this->accessToken())->timeout(20)->get(self::API.$path);
        if (! $response->successful()) throw new RuntimeException('Search Console API a returnat HTTP '.$response->status().'. Verifică accesul contului și proprietatea verificată.');

        return $response->json() ?? [];
    }
}
