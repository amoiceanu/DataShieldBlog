@extends('admin.layout')
@section('title', 'Sfatul zilei — Administrare')
@section('content')
<div class="admin-heading"><div><h1>Sfatul zilei</h1><p>Scrie și programează sfaturi care apar pe prima pagină.</p></div><a class="admin-button primary" href="{{ route('admin.sfaturi.create') }}"><i class="bi bi-plus-lg"></i> Sfat nou</a></div>
<section class="admin-stats" aria-label="Statistici sfaturi">
    <article class="admin-stat-card"><div class="admin-stat-label"><span>Total sfaturi</span><i class="bi bi-lightbulb" aria-hidden="true"></i></div><strong>{{ number_format($stats['total']) }}</strong><small>În biblioteca editorială</small></article>
    <article class="admin-stat-card"><div class="admin-stat-label"><span>Publicate</span><i class="bi bi-globe2" aria-hidden="true"></i></div><strong>{{ number_format($stats['published']) }}</strong><small>Disponibile pentru rotația zilnică</small></article>
    <article class="admin-stat-card"><div class="admin-stat-label"><span>Drafturi</span><i class="bi bi-pencil-square" aria-hidden="true"></i></div><strong>{{ number_format($stats['drafts']) }}</strong><small>Vizibile doar în administrare</small></article>
</section>
<section class="admin-panel">
    <table class="admin-table"><thead><tr><th>Sfat</th><th>Stare</th><th>Data publicării</th><th>Acțiuni</th></tr></thead><tbody>
        @forelse($tips as $tip)
            <tr><td><span class="row-title">{{ $tip->title }}</span><small>{{ Str::limit($tip->body, 140) }}</small></td><td><span class="admin-badge {{ $tip->status === 'draft' ? 'draft' : 'published' }}">{{ $tip->status === 'draft' ? 'Draft' : ($tip->published_at?->isFuture() ? 'Programat' : 'Publicat') }}</span></td><td>{{ $tip->published_at?->format('d.m.Y') ?? '—' }}</td><td><a class="admin-button" href="{{ route('admin.sfaturi.edit', $tip) }}">Editează</a> <form class="admin-inline" action="{{ route('admin.sfaturi.destroy', $tip) }}" method="post" onsubmit="return confirm('Ștergi acest sfat?')">@csrf @method('delete')<button class="admin-button danger" type="submit">Șterge</button></form></td></tr>
        @empty<tr><td colspan="4">Nu există sfaturi. Creează primul sfat ca să apară pe prima pagină.</td></tr>@endforelse
    </tbody></table><div class="admin-pagination">{{ $tips->links() }}</div>
</section>
@endsection
