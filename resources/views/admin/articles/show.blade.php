@extends('admin.layout')
@section('title', $article->title.' — Previzualizare administrare')
@section('content')
<div class="admin-heading"><div><h1>Previzualizare articol</h1><p>Vizualizare administrativă — articolul nu este publicat pe site dacă este draft.</p></div><div class="admin-preview-actions"><a class="admin-button" href="{{ route('admin.articole.index', ['sectiune' => $article->category_id]) }}">Înapoi la articole</a><a class="admin-button primary" href="{{ route('admin.articole.edit', $article) }}"><i class="bi bi-pencil-square" aria-hidden="true"></i> Editează</a></div></div>
<article class="admin-article-preview admin-panel">
    <div class="admin-preview-meta"><span class="admin-badge {{ $article->status === 'draft' ? 'draft' : ($article->published_at?->isFuture() ? 'scheduled' : 'published') }}">{{ $article->status === 'draft' ? 'Draft' : ($article->published_at?->isFuture() ? 'Programat' : 'Publicat') }}</span><span>{{ $article->category->name }}</span><span>{{ $article->published_at?->format('d.m.Y') ?? 'Fără dată de publicare' }}</span><span>{{ $article->reading_time }} min citire</span></div>
    <h1>{{ $article->title }}</h1>
    <p class="admin-preview-excerpt">{{ $article->excerpt }}</p>
    @if($article->image)<img class="admin-preview-image" src="{{ asset('images/articles/'.$article->image) }}" alt="Imagine pentru {{ $article->title }}" onerror="this.hidden=true">@endif
    <div class="admin-preview-content">{!! $article->content !!}</div>
</article>
<div class="admin-preview-footer"><a class="admin-button primary" href="{{ route('admin.articole.edit', $article) }}"><i class="bi bi-pencil-square" aria-hidden="true"></i> Editează articolul</a></div>
@endsection
