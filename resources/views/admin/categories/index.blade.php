@extends('admin.layout')
@section('title', 'Secțiuni — Administrare')
@section('content')
<div class="admin-heading"><div><h1>Secțiuni</h1><p>Organizează articolele în categorii.</p></div><a class="admin-button primary" href="{{ route('admin.sectiuni.create') }}"><i class="bi bi-plus-lg"></i> Secțiune nouă</a></div>
<section class="admin-panel">
    <table class="admin-table"><thead><tr><th>Secțiune</th><th>Slug</th><th>Articole</th><th>Acțiuni</th></tr></thead><tbody>
        @forelse($categories as $category)
            <tr><td><span class="row-title">{{ $category->name }}</span><small>{{ $category->description }}</small></td><td>{{ $category->slug }}</td><td><a class="admin-table-link" href="{{ route('admin.articole.index', ['sectiune' => $category->id]) }}" aria-label="Vezi toate articolele din secțiunea {{ $category->name }}">{{ $category->articles_count }} articole</a></td><td><a class="admin-button" href="{{ route('admin.sectiuni.edit', $category) }}">Editează</a> <form class="admin-inline" action="{{ route('admin.sectiuni.destroy', $category) }}" method="post" onsubmit="return confirm('Ștergi această secțiune?')">@csrf @method('delete')<button class="admin-button danger" type="submit">Șterge</button></form></td></tr>
        @empty<tr><td colspan="4">Nu există secțiuni.</td></tr>@endforelse
    </tbody></table><div class="admin-pagination">{{ $categories->links() }}</div>
</section>
@endsection
