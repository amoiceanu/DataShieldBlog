@extends('admin.layout')
@section('title', 'Google Indexing — Administrare')
@section('content')
<div class="admin-heading">
    <div><h1>Google Indexing</h1><p>Audit tehnic pentru URL-urile publice și integrarea autorizată cu Search Console.</p></div>
    <div class="google-indexing-toolbar">
        <form method="post" action="{{ route('admin.google-indexing.validate-sitemap') }}">@csrf<button class="admin-button" type="submit">Validează sitemap</button></form>
        <form method="post" action="{{ route('admin.google-indexing.audit') }}">@csrf<button class="admin-button primary" type="submit">Rulează audit</button></form>
        <a class="admin-button" href="{{ route('admin.google-indexing.export') }}">Exportă URL-uri CSV</a>
    </div>
</div>
@if(session('sitemap_validation'))<div class="admin-success" role="status">Sitemap XML valid: {{ session('sitemap_validation.count') }} URL-uri, verificat {{ session('sitemap_validation.validated_at') }}.</div>@endif
<section class="admin-stats google-indexing-stats" aria-label="Indexare: statistici">
    <article class="admin-stat-card"><div class="admin-stat-label"><span>Articole publicate</span><i class="bi bi-file-earmark-text" aria-hidden="true"></i></div><strong>{{ number_format($summary['published_articles']) }}</strong><small>Eligibile după stare și dată</small></article>
    <article class="admin-stat-card"><div class="admin-stat-label"><span>URL-uri de secțiune</span><i class="bi bi-collection" aria-hidden="true"></i></div><strong>{{ number_format($summary['eligible_categories']) }}</strong><small>Secțiuni cu articole publice</small></article>
    <article class="admin-stat-card"><div class="admin-stat-label"><span>Indexabile local</span><i class="bi bi-check2-circle" aria-hidden="true"></i></div><strong>{{ $summary['locally_indexable'] === null ? '—' : number_format($summary['locally_indexable']) }}</strong><small>Confirmate la ultimul audit</small></article>
    <article class="admin-stat-card"><div class="admin-stat-label"><span>URL-uri în sitemap</span><i class="bi bi-diagram-3" aria-hidden="true"></i></div><strong>{{ $summary['sitemap_urls'] === null ? '—' : number_format($summary['sitemap_urls']) }}</strong><small>Din sitemap-ul public</small></article>
</section>
<section class="admin-panel google-indexing-summary">
    <div><span>Sitemap înregistrat</span><strong>{{ $summary['sitemap_urls'] === null ? 'Neverificat' : number_format($summary['sitemap_urls']).' URL-uri' }}</strong></div>
    <div><span>Probleme tehnice</span><strong>{{ $summary['technical_problems'] === null ? 'Audit necesar' : number_format($summary['technical_problems']) }}</strong></div>
    <div><span>robots.txt</span><strong class="{{ $summary['robots_valid'] === true ? 'good' : ($summary['robots_valid'] === false ? 'bad' : '') }}">{{ $summary['robots_valid'] === null ? 'Neverificat' : ($summary['robots_valid'] ? 'Valid' : 'Problemă') }}</strong></div>
    <div><span>Search Console</span><strong class="{{ $connection && $propertyAvailable ? 'good' : 'muted' }}">{{ !$connection ? 'Neconectat' : ($propertyAvailable ? 'Conectat' : 'Proprietate indisponibilă') }}</strong></div>
    <div><span>Ultima trimitere sitemap</span><strong>{{ $sitemapStatus['lastSubmitted'] ?? ($connection?->last_sitemap_submission_at?->toDateTimeString() ?? 'Necunoscută') }}</strong></div>
    <div><span>Stare Google</span><strong class="muted">{{ $items->whereIn('google_status', ['indexed', 'not_indexed'])->count() }} URL-uri verificate; {{ $items->whereNull('google_status')->count() }} necunoscute.</strong></div>
</section>
<section class="admin-panel google-connection-panel">
    <div><h2>Google Search Console</h2><p>Proprietate de domeniu: <code>sc-domain:securitatedigitala.ro</code></p>
        @if(!$google->configured())<p class="google-muted">OAuth nu este configurat. Adaugă valorile reale din Google Cloud în variabilele de mediu ale aplicației.</p>@elseif(!$connection)<p class="google-muted">Conectarea necesită un cont cu acces la proprietatea verificată.</p>@elseif(!$propertyAvailable)<p class="google-muted">Cont conectat. Proprietatea de domeniu nu apare în lista Google pentru acest cont.</p>@else<p class="google-muted">Token-urile OAuth sunt criptate în baza de date. Ultima actualizare Google: {{ $connection->last_google_refresh_at?->toDateTimeString() ?? 'încă neefectuată' }}.</p>@endif
        @if($googleError)<p class="google-error">{{ $googleError }}</p>@endif
        @if($connection?->performance_data)<p class="google-metrics">Ultimele 28 zile disponibile ({{ $connection->performance_data['period']['from'] ?? '' }} – {{ $connection->performance_data['period']['to'] ?? '' }}): {{ number_format((float) data_get($connection->performance_data, 'performance.clicks', 0)) }} clicuri · {{ number_format((float) data_get($connection->performance_data, 'performance.impressions', 0)) }} afișări · CTR {{ number_format((float) data_get($connection->performance_data, 'performance.ctr', 0) * 100, 2) }}% · poziție medie {{ data_get($connection->performance_data, 'performance.position', '—') }}.</p>@endif
        @if($sitemapStatus)<p class="google-muted">Descărcat de Google: {{ $sitemapStatus['lastDownloaded'] ?? 'necunoscut' }} · în procesare: {{ ($sitemapStatus['isPending'] ?? false) ? 'da' : 'nu' }} · URL-uri cunoscute: {{ data_get($sitemapStatus, 'contents.0.indexed', 'necunoscut') }}.</p>@if(!empty($sitemapStatus['errors']))<p class="google-error">Erori sitemap: {{ collect($sitemapStatus['errors'])->pluck('errors')->flatten()->pluck('message')->filter()->implode('; ') ?: json_encode($sitemapStatus['errors'], JSON_UNESCAPED_UNICODE) }}</p>@endif @if(!empty($sitemapStatus['warnings']))<p class="google-muted">Avertizări sitemap: {{ collect($sitemapStatus['warnings'])->pluck('warnings')->flatten()->pluck('message')->filter()->implode('; ') ?: json_encode($sitemapStatus['warnings'], JSON_UNESCAPED_UNICODE) }}</p>@endif @endif
    </div>
    <div class="google-connection-actions">
        @if(!$connection || !$propertyAvailable)
            <form method="post" action="{{ route('admin.google-indexing.connect') }}">@csrf<button class="admin-button primary" type="submit" @disabled(!$google->configured())>Conectează Search Console</button></form>
        @else
            <form method="post" action="{{ route('admin.google-indexing.sitemap') }}">@csrf<button class="admin-button" type="submit">Trimite sitemap</button></form>
            <form method="post" action="{{ route('admin.google-indexing.refresh') }}">@csrf<button class="admin-button" type="submit">Actualizează datele Google</button></form>
            <form method="post" action="{{ route('admin.google-indexing.disconnect') }}">@csrf @method('delete')<button class="admin-button danger" type="submit">Deconectează</button></form>
        @endif
    </div>
</section>
<section class="admin-panel google-indexing-table-wrap">
    <div class="google-table-heading"><div><h2>URL-uri publice</h2><p>Un URL devine „indexabil local” doar dacă trece verificările HTTP, canonical, robots, sitemap și descoperire internă.</p></div>@if($audit)<span class="google-audit-state">Audit #{{ $audit->id }} · {{ $audit->status }} · {{ $audit->completed_at?->toDateTimeString() ?? $audit->created_at->toDateTimeString() }}</span>@endif</div>
    <table class="admin-table google-indexing-table"><thead><tr><th>Titlu</th><th>Tip</th><th>HTTP</th><th>Canonical</th><th>Sitemap</th><th>Indexabilitate locală</th><th>Stare Google</th><th>Acțiuni</th></tr></thead><tbody>
    @forelse($items as $item)
        <tr>
            <td><span class="row-title">{{ $item->title }}</span><small>{{ $item->url }}</small>@if($item->problems)<small class="google-problems">{{ implode(', ', $item->problems) }}</small>@endif</td>
            <td>{{ $item->type }}</td><td>{{ $item->http_status ?? '—' }}</td>
            <td>@if($item->canonical_url)<a class="admin-table-link" href="{{ $item->canonical_url }}" target="_blank" rel="noopener">Vezi canonical</a>@else—@endif</td>
            <td>{{ $item->sitemap_included ? 'Da' : ($audit?->status === 'completed' ? 'Nu' : '—') }}</td>
            <td><span class="admin-badge {{ $item->local_status === 'eligible' ? 'published' : ($item->local_status === 'invalid' ? 'draft' : '') }}">{{ ['eligible' => 'Indexabil local', 'invalid' => 'Problemă locală', 'not_checked' => 'Neverificat'][$item->local_status] ?? $item->local_status }}</span></td>
            <td>@if($item->google_status === 'indexed')<span class="admin-badge published">Indexat de Google</span>@elseif($item->google_status === 'not_indexed')<span class="admin-badge draft">Neindexat de Google</span>@else<span class="google-muted">Necunoscut</span>@endif</td>
            <td class="google-row-actions"><a class="admin-button" href="{{ $item->url }}" target="_blank" rel="noopener">Deschide</a><button class="admin-button" type="button" data-copy-url="{{ $item->canonical_url ?: $item->url }}">Copiază URL</button><form method="post" action="{{ route('admin.google-indexing.audit') }}">@csrf<button class="admin-button" type="submit">Reverifică site-ul</button></form><a class="admin-button" href="https://search.google.com/search-console/inspect?resource_id={{ urlencode('sc-domain:securitatedigitala.ro') }}&amp;url={{ urlencode($item->canonical_url ?: $item->url) }}" target="_blank" rel="noopener">Deschide URL Inspection</a>@if($connection && $propertyAvailable && $item->id)<form method="post" action="{{ route('admin.google-indexing.inspect', $item) }}">@csrf<button class="admin-button" type="submit">Verifică în Google</button></form>@endif</td>
        </tr>
    @empty<tr><td colspan="8">Nu există URL-uri eligibile de verificat.</td></tr>@endforelse
    </tbody></table>
</section>
<script>document.querySelectorAll('[data-copy-url]').forEach((button)=>button.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(button.dataset.copyUrl);button.textContent='Copiat';setTimeout(()=>button.textContent='Copiază URL',1500)}catch{button.textContent='Copiază manual din link'}}));</script>
@endsection
