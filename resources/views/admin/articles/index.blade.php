@extends('admin.layout')
@section('title', 'Articole — Administrare')
@section('content')
<div class="admin-heading"><div><h1>Articole</h1><p>@if($filteredCategory)Articole din secțiunea {{ $filteredCategory->name }}. @else Gestionează articolele publice și drafturile. @endif</p>@if($filteredCategory)<a class="admin-table-link" href="{{ route('admin.articole.index') }}">Vezi toate articolele</a>@endif</div><a class="admin-button primary" href="{{ route('admin.articole.create') }}"><i class="bi bi-plus-lg"></i> Articol nou</a></div>
<section class="admin-panel">
    <table class="admin-table"><thead><tr><th>Articol</th><th>Secțiune</th><th>Stare</th><th>Data publicării</th><th>Acțiuni</th></tr></thead><tbody>
        @forelse($articles as $article)
            <tr><td><span class="row-title">{{ $article->title }}</span><small>/{{ $article->slug }}</small></td><td>{{ $article->category->name }}</td><td><span class="admin-badge {{ $article->status === 'draft' ? 'draft' : ($article->published_at?->isFuture() ? 'scheduled' : 'published') }}">{{ $article->status === 'draft' ? 'Draft' : ($article->published_at?->isFuture() ? 'Programat' : 'Publicat') }}</span></td><td>{{ $article->published_at?->format('d.m.Y') ?? '—' }}</td><td><a class="admin-button" href="{{ route('admin.articole.edit', $article) }}">Editează</a> <form class="admin-inline" action="{{ route('admin.articole.destroy', $article) }}" method="post" onsubmit="return confirm('Ștergi acest articol?')">@csrf @method('delete')<button class="admin-button danger" type="submit">Șterge</button></form></td></tr>
        @empty<tr><td colspan="5">Nu există articole.</td></tr>@endforelse
    </tbody></table><div class="admin-pagination">{{ $articles->links() }}</div>
</section>
@endsection
