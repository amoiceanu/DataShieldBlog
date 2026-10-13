@extends('layouts.app')
@section('title', 'Articole despre securitate digitală — Securitate Digitală')
@section('description', 'Ghiduri practice despre protecția datelor personale, phishing, fraude online, parole, autentificare și siguranță digitală.')
@if(request()->filled('categorie') || request()->filled('q') || request()->filled('page'))@section('robots', 'noindex,follow')@endif
@section('content')
<section class="page-hero container">
    <span class="eyebrow">SECURITATE DIGITALĂ</span>
    <h1>Articole despre <span>securitate digitală</span></h1>
    <p>Informații clare și pași practici pentru a-ți proteja datele și conturile online.</p>
</section>
<section class="container section article-listing">
    <form class="filter-bar" action="{{ route('articles.index') }}" method="get">
        <label class="search-field"><i class="bi bi-search"></i><input name="q" value="{{ request('q') }}" placeholder="Caută articole..." aria-label="Caută articole"></label>
        <label class="visually-hidden" for="categoryFilter">Filtrează după categorie</label>
        <select id="categoryFilter" name="categorie" onchange="this.form.submit()">
            <option value="">Toate categoriile</option>
            @foreach($categories as $category)<option value="{{ $category->slug }}" @selected(request('categorie') === $category->slug)>{{ $category->name }}</option>@endforeach
        </select>
        <button class="btn btn-primary" type="submit">Caută</button>
    </form>
    @if(request()->filled('q') || request()->filled('categorie'))<p class="results-note">{{ $articles->total() }} rezultate găsite</p>@endif
    <div class="article-grid">
        @forelse($articles as $article)
            <x-article-card :article="$article" />
        @empty
            <div class="empty-state"><i class="bi bi-search"></i><h2>Nu am găsit articole</h2><p>Încearcă alți termeni sau selectează o categorie diferită.</p><a class="btn btn-outline-light" href="{{ route('articles.index') }}">Vezi toate articolele</a></div>
        @endforelse
    </div>
    <div class="pagination-wrap">{{ $articles->links('pagination::bootstrap-5') }}</div>
</section>
@endsection
