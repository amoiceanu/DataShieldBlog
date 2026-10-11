@extends('admin.layout')
@section('title', ($article->exists ? 'Editează articol' : 'Articol nou').' — Administrare')
@section('content')
<div class="admin-heading"><div><h1>{{ $article->exists ? 'Editează articolul' : 'Articol nou' }}</h1><p>Drafturile sunt vizibile doar în administrare. Publicarea le face accesibile vizitatorilor.</p></div><a class="admin-button" href="{{ route('admin.articole.index') }}">Înapoi la articole</a></div>
<section class="admin-panel">
    <form class="admin-form" method="post" action="{{ $article->exists ? route('admin.articole.update', $article) : route('admin.articole.store') }}">
        @csrf @if($article->exists) @method('put') @endif
        <div class="admin-form-grid">
            <div class="admin-field"><label for="title">Titlu</label><input id="title" name="title" value="{{ old('title', $article->title) }}" required maxlength="255"></div>
            <div class="admin-field"><label for="slug">Adresă (slug, opțional)</label><input id="slug" name="slug" value="{{ old('slug', $article->slug) }}" placeholder="generat-din-titlu"></div>
            <div class="admin-field"><label for="category_id">Secțiune</label><select id="category_id" name="category_id" required><option value="">Alege secțiunea</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id', $article->category_id) == $category->id)>{{ $category->name }}</option>@endforeach</select></div>
            <div class="admin-field"><label for="author">Autor</label><input id="author" name="author" value="{{ old('author', $article->author ?: 'Redacția Securitate Digitală') }}" required maxlength="255"></div>
            <div class="admin-field full"><label for="excerpt">Descriere scurtă</label><textarea id="excerpt" name="excerpt" rows="3" maxlength="1000" required>{{ old('excerpt', $article->excerpt) }}</textarea></div>
            <div class="admin-field full"><label for="content">Conținut (HTML permis)</label><textarea id="content" name="content" rows="18" required>{{ old('content', $article->content) }}</textarea></div>
            <div class="admin-field"><label for="image">Imagine (cale din public, opțional)</label><input id="image" name="image" value="{{ old('image', $article->image) }}" placeholder="images/articles/exemplu.svg"></div>
            <div class="admin-field"><label for="reading_time">Timp de citire (minute)</label><input id="reading_time" name="reading_time" type="number" min="1" max="65535" value="{{ old('reading_time', $article->reading_time ?? 5) }}" required></div>
            <div class="admin-field"><label for="status">Stare</label><select id="status" name="status" required><option value="draft" @selected(old('status', $article->status) === 'draft')>Draft</option><option value="published" @selected(old('status', $article->status) === 'published')>Publicat</option></select></div>
            <div class="admin-field"><label for="published_at">Data publicării</label><input id="published_at" name="published_at" type="date" value="{{ old('published_at', $article->published_at?->format('Y-m-d')) }}"><small>Data viitoare programează articolul pentru publicare.</small></div>
        </div>
        <div class="admin-checks"><label class="admin-check"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $article->is_featured))> Evidențiază pe prima pagină</label></div>
        <div><button class="admin-button primary" type="submit">{{ $article->exists ? 'Salvează modificările' : 'Creează articolul' }}</button></div>
    </form>
</section>
@endsection
