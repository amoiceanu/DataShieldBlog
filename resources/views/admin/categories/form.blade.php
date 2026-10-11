@extends('admin.layout')
@section('title', ($category->exists ? 'Editează secțiunea' : 'Secțiune nouă').' — Administrare')
@section('content')
<div class="admin-heading"><div><h1>{{ $category->exists ? 'Editează secțiunea' : 'Secțiune nouă' }}</h1><p>Secțiunile apar pe site dacă au articole publicate.</p></div><a class="admin-button" href="{{ route('admin.sectiuni.index') }}">Înapoi la secțiuni</a></div>
<section class="admin-panel"><form class="admin-form" method="post" action="{{ $category->exists ? route('admin.sectiuni.update', $category) : route('admin.sectiuni.store') }}">
    @csrf @if($category->exists) @method('put') @endif
    <div class="admin-form-grid">
        <div class="admin-field"><label for="name">Nume</label><input id="name" name="name" value="{{ old('name', $category->name) }}" required maxlength="255"></div>
        <div class="admin-field"><label for="slug">Slug (opțional)</label><input id="slug" name="slug" value="{{ old('slug', $category->slug) }}" placeholder="generat-din-nume"></div>
        <div class="admin-field full"><label for="description">Descriere</label><textarea id="description" name="description" rows="4" maxlength="1000">{{ old('description', $category->description) }}</textarea></div>
    </div>
    <div><button class="admin-button primary" type="submit">{{ $category->exists ? 'Salvează modificările' : 'Creează secțiunea' }}</button></div>
</form></section>
@endsection
