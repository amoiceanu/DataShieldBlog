<header class="site-header">
    <nav class="navbar container" aria-label="Navigare principală">
        <a class="brand" href="{{ route('home') }}" aria-label="Securitate Digitală">
            <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
            <span>Securitate <b>Digitală</b></span>
        </a>
        <div class="nav-links" id="navLinks">
            <a class="{{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">Home</a>
            <a class="{{ request()->routeIs('articles.*') ? 'active' : '' }}" href="{{ route('articles.index') }}">Articole</a>
            <a href="{{ route('articles.index', ['categorie' => 'ghiduri']) }}">Ghiduri</a>
            <a class="{{ request()->routeIs('about') ? 'active' : '' }}" href="{{ route('about') }}">Despre</a>
            <a class="{{ request()->routeIs('contact') ? 'active' : '' }}" href="{{ route('contact') }}">Contact</a>
        </div>
        <div class="nav-tools">
            <button class="icon-button search-toggle" aria-label="Caută articole" type="button"><i class="bi bi-search" aria-hidden="true"></i></button>
            <button class="icon-button menu-toggle" aria-label="Deschide meniul" aria-expanded="false" aria-controls="navLinks" type="button"><i class="bi bi-list" aria-hidden="true"></i></button>
        </div>
    </nav>
</header>
