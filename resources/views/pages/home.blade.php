@extends('layouts.app')
@section('title', 'Securitate Digitală — Protejează-ți viața online.')
@section('description', 'Securitate Digitală oferă ghiduri practice despre protecția datelor personale, phishing, fraude online, parole, autentificare și siguranță digitală.')
@section('content')
<section class="hero container">
    <div class="hero-copy">
        <span class="eyebrow"><i class="bi bi-shield-check"></i> SECURITATE DIGITALĂ</span>
        <h1>Protejează-ți viața <span>online.</span></h1>
        <p>Ghiduri practice despre securitate online, protecția datelor personale, phishing, fraude digitale și confidențialitate.</p>
        <div class="hero-actions">
            <a class="btn btn-primary" href="{{ route('articles.index', ['categorie' => 'ghiduri']) }}">Citește ghidurile <i class="bi bi-arrow-right"></i></a>
            <a class="btn btn-outline-light" href="#ultimele">Cele mai noi articole</a>
        </div>
        <div class="trust-note"><i class="bi bi-check-circle-fill"></i> Sfaturi clare. Mai mult control. Mai multă siguranță.</div>
    </div>
    <div class="hero-art" aria-label="Ilustrație abstractă de securitate digitală">
        <div class="orbit orbit-one"></div>
        <div class="orbit orbit-two"></div>
        <div class="shield-art"><i class="bi bi-shield-lock-fill"></i><span class="shield-spark"></span></div>
        <div class="float-card float-top"><i class="bi bi-lock-fill"></i> Date protejate</div>
        <div class="float-card float-bottom"><i class="bi bi-activity"></i> Securitate activă</div>
        <div class="matrix">01&nbsp; 10&nbsp; 11<br>10&nbsp; 01&nbsp; 01<br>11&nbsp; 10&nbsp; 00</div>
    </div>
</section>
<section class="section container" id="ultimele">
    <div class="section-heading">
        <div><span class="eyebrow">DIN REDACȚIE</span><h2>Ultimele articole<span>.</span></h2></div>
        <a class="text-link" href="{{ route('articles.index') }}">Toate articolele <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="article-grid">@foreach($articles as $article)<x-article-card :article="$article" />@endforeach</div>
</section>
<section class="section category-section">
    <div class="container">
        <div class="section-heading"><div><span class="eyebrow">GĂSEȘTE CE CAUȚI</span><h2>Explorează după categorie<span>.</span></h2></div></div>
        <div class="category-grid">
            @php($icons = ['bi-shield-check','bi-person-vcard','bi-envelope-exclamation','bi-key','bi-people','bi-eye-slash','bi-exclamation-triangle','bi-journal-check'])
            @foreach($categories as $index => $category)
                <a class="category-tile" href="{{ route('articles.index', ['categorie' => $category->slug]) }}"><i class="bi {{ $icons[$index % count($icons)] }}"></i><span>{{ $category->name }}</span><i class="bi bi-arrow-up-right arrow"></i></a>
            @endforeach
        </div>
    </div>
</section>
<section class="container newsletter-cta">
    <div><span class="eyebrow">SIGURANȚA ÎNCEPE CU INFORMAȚIA</span><h2>Fă din securitate un obicei.</h2><p>Idei practice, pe înțelesul tuturor. Descoperă ghidurile Securitate Digitală.</p></div>
    <a class="btn btn-primary" href="{{ route('articles.index') }}">Explorează articolele <i class="bi bi-arrow-right"></i></a>
</section>
@endsection
