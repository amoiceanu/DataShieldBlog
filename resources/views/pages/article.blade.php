@extends('layouts.app')
@section('title', ($article->seo_title ?: $article->title).' — Securitate Digitală')
@section('description', $article->meta_description ?: $article->excerpt)
@section('og_type', 'article')
@section('og_image', asset('images/articles/'.$article->image))
@push('head')
@if($article->focus_keyword || $article->secondary_keywords)<meta name="keywords" content="{{ implode(', ', array_filter([$article->focus_keyword, ...($article->secondary_keywords ?? [])])) }}">@endif
<meta property="article:published_time" content="{{ $article->published_at->toAtomString() }}">
<script type="application/ld+json">{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => $article->title,
    'description' => $article->excerpt,
    'image' => 'https://securitatedigitala.ro/images/articles/'.$article->image,
    'datePublished' => $article->published_at->toAtomString(),
    'author' => ['@type' => 'Organization', 'name' => $article->author],
    'publisher' => ['@type' => 'Organization', 'name' => 'Securitate Digitală', 'url' => 'https://securitatedigitala.ro'],
    'mainEntityOfPage' => 'https://securitatedigitala.ro/articole/'.$article->slug,
], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
@endpush
@section('content')
<section class="article-hero container">
    <div class="article-hero-copy">
        <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><i class="bi bi-chevron-right"></i><a href="{{ route('articles.index') }}">Articole</a><i class="bi bi-chevron-right"></i><span>{{ $article->category->name }}</span></nav>
        <x-category-badge :category="$article->category" />
        <h1>{{ $article->title }}</h1>
        <p class="article-excerpt">{{ $article->excerpt }}</p>
        <div class="author-meta"><span class="author-avatar"><i class="bi bi-shield-lock"></i></span><span><b>{{ $article->author }}</b><small><time datetime="{{ $article->published_at->toDateString() }}">{{ $article->published_at->translatedFormat('j F Y') }}</time> · <x-reading-time :minutes="$article->reading_time" /></small></span></div>
    </div>
    <div class="article-cover"><img src="{{ asset('images/articles/'.$article->image) }}" alt="Ilustrație pentru {{ $article->title }}" onerror="this.style.display='none'"></div>
</section>
<div class="container article-layout">
    <article class="article-content">
        {!! $article->content !!}
        <x-security-callout>Protecția datelor personale începe cu obiceiuri digitale simple, repetate consecvent.</x-security-callout>
        <div class="article-end"><span>Ți-a fost util acest ghid?</span><a href="{{ route('articles.index') }}" class="text-link">Descoperă și alte articole <i class="bi bi-arrow-right"></i></a></div>
    </article>
    <aside class="article-sidebar">
        <h2>Articole recomandate</h2>
        @foreach($recommended as $suggestion)<x-recommended-article :article="$suggestion" />@endforeach
        <div class="sidebar-cta"><i class="bi bi-shield-check"></i><h3>Vrei mai multe ghiduri practice?</h3><p>Urmărește Securitate Digitală pentru articole noi despre confidențialitate, phishing, parole și siguranță online.</p><a class="btn btn-primary" href="{{ route('articles.index') }}">Vezi toate articolele</a></div>
    </aside>
</div>
@endsection
