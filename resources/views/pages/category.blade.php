@extends('layouts.app')
@section('title', $category->name.' — Securitate Digitală')
@section('description', $category->description ?: 'Articole și ghiduri despre '.$category->name.'.')
@section('canonical', route('sections.show', $category->slug))
@if(request()->filled('page'))@section('robots', 'noindex,follow')@endif
@section('content')
<section class="page-hero container">
    <span class="eyebrow"><a href="{{ route('articles.index') }}">SECURITATE DIGITALĂ</a></span>
    <h1>{{ $category->name }}</h1>
    <p>{{ $category->description ?: 'Ghiduri practice și informații utile despre '.$category->name.'.' }}</p>
</section>
<section class="container section article-listing">
    <p class="results-note">{{ $articles->total() }} {{ $articles->total() === 1 ? 'articol publicat' : 'articole publicate' }}</p>
    <div class="article-grid">
        @forelse($articles as $article)<x-article-card :article="$article" />
        @empty<div class="empty-state"><h2>Nu există articole publicate în această secțiune.</h2></div>@endforelse
    </div>
    <div class="pagination-wrap">{{ $articles->links() }}</div>
</section>
@endsection
