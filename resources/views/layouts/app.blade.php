<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Securitate Digitală — Protejează-ți viața online.')</title>
    <meta name="description" content="@yield('description', 'Securitate Digitală oferă ghiduri practice despre protecția datelor personale, phishing, fraude online, parole, autentificare și siguranță digitală.')">
    <link rel="canonical" href="https://securitatedigitala.ro{{ request()->getPathInfo() === '/' ? '' : request()->getPathInfo() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/securitate-digitala-icon.svg') }}">
    <meta property="og:site_name" content="Securitate Digitală">
    <meta property="og:title" content="@yield('title', 'Securitate Digitală — Protejează-ți viața online.')">
    <meta property="og:description" content="@yield('description', 'Securitate Digitală oferă ghiduri practice despre protecția datelor personale, phishing, fraude online, parole, autentificare și siguranță digitală.')">
    <meta property="og:url" content="https://securitatedigitala.ro{{ request()->getPathInfo() === '/' ? '' : request()->getPathInfo() }}">
    <meta property="og:image" content="{{ asset('images/articles/password-security.svg') }}">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title', 'Securitate Digitală — Protejează-ți viața online.')">
    <meta name="twitter:description" content="@yield('description', 'Securitate Digitală oferă ghiduri practice despre protecția datelor personale, phishing, fraude online, parole, autentificare și siguranță digitală.')">
    <script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => 'Securitate Digitală', 'url' => 'https://securitatedigitala.ro', 'logo' => 'https://securitatedigitala.ro/images/securitate-digitala-icon.svg'], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
    <script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => 'Securitate Digitală', 'url' => 'https://securitatedigitala.ro', 'description' => 'Protejează-ți viața online.'], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    @stack('head')
</head>
<body>
    <a class="skip-link" href="#main">Sari la conținut</a>
    <x-navbar />
    <main id="main">@yield('content')</main>
    <x-footer />
    <div class="search-overlay" aria-hidden="true">
        <button class="search-close icon-button" aria-label="Închide căutarea"><i class="bi bi-x-lg"></i></button>
        <form action="{{ route('articles.index') }}" method="get">
            <label for="overlaySearch">Caută în Securitate Digitală</label>
            <div>
                <input id="overlaySearch" name="q" type="search" placeholder="Caută articole..." required>
                <button class="btn btn-primary" type="submit" aria-label="Caută"><i class="bi bi-search"></i></button>
            </div>
        </form>
    </div>
    <div class="cookie-notice" hidden>
        <p>Acest site folosește doar cookie-uri strict necesare funcționării. Nu folosim cookie-uri de analiză sau publicitate. <a href="{{ route('cookies') }}">Detalii</a></p>
        <button type="button" class="btn btn-outline-light cookie-accept">Am înțeles</button>
    </div>
    @stack('scripts')
</body>
</html>
