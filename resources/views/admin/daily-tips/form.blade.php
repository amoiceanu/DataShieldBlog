@extends('admin.layout')
@section('title', ($dailyTip->exists ? 'Editează sfatul' : 'Sfat nou').' — Administrare')
@section('content')
<div class="admin-heading"><div><h1>{{ $dailyTip->exists ? 'Editează sfatul zilei' : 'Sfat nou' }}</h1><p>Publică imediat, programează pentru o dată viitoare sau păstrează ca draft.</p></div><a class="admin-button" href="{{ route('admin.sfaturi.index') }}">Înapoi la sfaturi</a></div>
<section class="admin-panel"><form class="admin-form" method="post" action="{{ $dailyTip->exists ? route('admin.sfaturi.update', $dailyTip) : route('admin.sfaturi.store') }}">
    @csrf @if($dailyTip->exists) @method('put') @endif
    <div class="admin-form-grid">
        <div class="admin-field full"><label for="title">Titlu scurt</label><input id="title" name="title" value="{{ old('title', $dailyTip->title) }}" maxlength="180" required></div>
        <div class="admin-field full"><label for="body">Sfat</label><textarea id="body" name="body" rows="5" maxlength="3000" required>{{ old('body', $dailyTip->body) }}</textarea><small>Textul va fi afișat ca text simplu pe prima pagină.</small></div>
        <div class="admin-field"><label for="status">Stare</label><select id="status" name="status" required><option value="draft" @selected(old('status', $dailyTip->status) === 'draft')>Draft</option><option value="published" @selected(old('status', $dailyTip->status) === 'published')>Publicat</option></select></div>
        <div class="admin-field"><label for="published_at">Data publicării</label><input id="published_at" name="published_at" type="date" value="{{ old('published_at', $dailyTip->published_at?->format('Y-m-d')) }}"><small>O dată viitoare programează sfatul.</small></div>
    </div>
    <div><button class="admin-button primary" type="submit">{{ $dailyTip->exists ? 'Salvează modificările' : 'Creează sfatul' }}</button></div>
</form></section>
@endsection
