<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <a class="brand" href="{{ route('home') }}" aria-label="Securitate Digitală">
                <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
                <span>Securitate <b>Digitală</b></span>
            </a>
            <p>Protejează-ți viața online.</p>
            <a href="https://securitatedigitala.ro">securitatedigitala.ro</a>
        </div>
        <div>
            <h2>Navigare</h2>
            <a href="{{ route('home') }}">Home</a>
            <a href="{{ route('articles.index') }}">Articole</a>
            <a href="{{ route('articles.index', ['categorie' => 'ghiduri']) }}">Ghiduri</a>
            <a href="{{ route('about') }}">Despre</a>
            <a href="{{ route('contact') }}">Contact</a>
        </div>
        <div>
            <h2>Categorii</h2>
            <a href="{{ route('articles.index', ['categorie' => 'securitate-online']) }}">Securitate online</a>
            <a href="{{ route('articles.index', ['categorie' => 'phishing']) }}">Phishing</a>
            <a href="{{ route('articles.index', ['categorie' => 'date-personale']) }}">Date personale</a>
        </div>
        <div>
            <h2>Informații</h2>
            <a href="{{ route('privacy') }}">Politica de confidențialitate</a>
            <a href="{{ route('cookies') }}">Politica cookies</a>
        </div>
    </div>
    <div class="container footer-bottom">
        <p>© 2026 Securitate Digitală. Toate drepturile rezervate.</p>
        <p>Conținut educativ și informativ. Nu reprezintă consultanță juridică sau de securitate informatică personalizată.</p>
    </div>
</footer>
