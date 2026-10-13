<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'Administrare — Securitate Digitală')</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/securitate-digitala-icon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/scss/app.scss', 'resources/scss/admin.scss', 'resources/js/app.js'])
</head>
<body class="admin-shell">
    <header class="admin-topbar">
        <a class="admin-brand" href="{{ route('admin.articole.index') }}"><i class="bi bi-shield-lock-fill"></i><span>Securitate Digitală <small>ADMIN</small></span></a>
        <div class="admin-actions">
            <nav class="admin-nav" aria-label="Administrare">
                <a class="{{ request()->routeIs('admin.articole.*') ? 'active' : '' }}" href="{{ route('admin.articole.index') }}">Articole</a>
                <a class="{{ request()->routeIs('admin.sectiuni.*') ? 'active' : '' }}" href="{{ route('admin.sectiuni.index') }}">Secțiuni</a>
                <a class="{{ request()->routeIs('admin.sfaturi.*') ? 'active' : '' }}" href="{{ route('admin.sfaturi.index') }}">Sfatul zilei</a>
                <a class="{{ request()->routeIs('admin.google-indexing.*') ? 'active' : '' }}" href="{{ route('admin.google-indexing.index') }}">Google Indexing</a>
                <a class="{{ request()->routeIs('admin.contact.*') ? 'active' : '' }}" href="{{ route('admin.contact.index') }}">Contact</a>
            </nav>
            <span class="admin-user">{{ auth()->user()->name }}</span>
            <form action="{{ route('admin.logout') }}" method="post">@csrf<button class="admin-button" type="submit">Ieșire</button></form>
        </div>
    </header>
    <main class="admin-main {{ request()->routeIs('admin.articole.*') ? 'admin-articles-page' : '' }} {{ request()->routeIs('admin.sectiuni.*') ? 'admin-sections-page' : '' }}">
        <div class="admin-workspace">
            <aside class="admin-sidebar" aria-label="Arbore secțiuni și articole">
                <label class="admin-tree-search-label" for="admin-tree-search">Caută în meniu</label>
                <div class="admin-tree-search"><i class="bi bi-search" aria-hidden="true"></i><input id="admin-tree-search" type="search" placeholder="Secțiune sau articol" autocomplete="off"><button type="button" id="admin-tree-clear" aria-label="Șterge căutarea" hidden><i class="bi bi-x-lg" aria-hidden="true"></i></button></div>
                <nav class="admin-tree" id="admin-tree" aria-label="Secțiuni și articole">
                    @forelse($adminSections as $section)
                        <details class="admin-tree-section" data-section-name="{{ mb_strtolower($section->name) }}" @if($section->articles->contains('id', request()->route('article')?->id)) open @endif>
                            <summary><i class="bi bi-chevron-right tree-chevron" aria-hidden="true"></i><span class="tree-section-name">{{ $section->name }}</span><span class="tree-count">{{ $section->articles->count() }}</span></summary>
                            <ul>
                                @forelse($section->articles as $treeArticle)
                                    <li><a class="admin-tree-article {{ request()->route('article')?->id === $treeArticle->id ? 'active' : '' }}" href="{{ route('admin.articole.show', $treeArticle) }}" data-article-title="{{ mb_strtolower($treeArticle->title) }}"><span class="tree-article-title">{{ $treeArticle->title }}</span><span class="admin-tree-status {{ $treeArticle->status === 'draft' ? 'draft' : 'published' }}" title="{{ $treeArticle->status === 'draft' ? 'Draft' : 'Publicat' }}" aria-label="{{ $treeArticle->status === 'draft' ? 'Draft' : 'Publicat' }}"></span></a></li>
                                @empty
                                    <li class="admin-tree-empty">Nu există articole</li>
                                @endforelse
                            </ul>
                        </details>
                    @empty
                        <p class="admin-tree-empty-state">Nu există secțiuni.</p>
                    @endforelse
                    <p class="admin-tree-no-results" id="admin-tree-no-results" hidden>Nu s-au găsit rezultate.</p>
                </nav>
                <a class="admin-tree-manage" href="{{ route('admin.sectiuni.index') }}"><i class="bi bi-gear" aria-hidden="true"></i> Gestionează secțiunile</a>
            </aside>
            <div class="admin-content">
                @if(session('success'))<div class="admin-success" role="status">{{ session('success') }}</div>@endif
                @if($errors->any())<div class="admin-errors" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                @yield('content')
            </div>
        </div>
    </main>
    <x-footer />
    <script>
        (() => {
            const input = document.getElementById('admin-tree-search');
            const clear = document.getElementById('admin-tree-clear');
            const noResults = document.getElementById('admin-tree-no-results');
            const sections = [...document.querySelectorAll('.admin-tree-section')];
            if (!input || !clear || !noResults) return;
            const filterTree = () => {
                const term = input.value.trim().toLocaleLowerCase();
                let visibleSections = 0;
                sections.forEach((section) => {
                    const sectionMatches = section.dataset.sectionName.includes(term);
                    const articles = [...section.querySelectorAll('.admin-tree-article')];
                    let visibleArticles = 0;
                    articles.forEach((article) => {
                        const matches = !term || sectionMatches || article.dataset.articleTitle.includes(term);
                        article.closest('li').hidden = !matches;
                        if (matches) visibleArticles++;
                    });
                    const visible = sectionMatches || visibleArticles > 0 || (!term && articles.length === 0);
                    section.hidden = !visible;
                    if (term && visible) section.open = true;
                    if (visible) visibleSections++;
                });
                noResults.hidden = !term || visibleSections > 0;
                clear.hidden = !input.value;
            };
            input.addEventListener('input', filterTree);
            clear.addEventListener('click', () => { input.value = ''; filterTree(); input.focus(); });
        })();
    </script>
</body>
</html>
