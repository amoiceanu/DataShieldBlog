@extends('admin.layout')
@section('title', 'Mesaje de contact — Administrare')
@section('content')
<div class="admin-heading">
    <div>
        <h1>Mesaje de contact</h1>
        <p>Mesajele trimise prin formularul public de contact.</p>
    </div>
</div>

<section class="admin-panel admin-contact-panel">
    <form class="admin-contact-search" action="{{ route('admin.contact.index') }}" method="get">
        <label class="admin-tree-search-label" for="contact-search">Caută mesaje</label>
        <div class="admin-tree-search">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input id="contact-search" name="q" type="search" value="{{ $search }}" placeholder="Nume, e-mail, subiect sau mesaj">
            <button class="admin-button" type="submit">Caută</button>
        </div>
    </form>

    <p class="admin-contact-count">{{ $messages->total() }} {{ $messages->total() === 1 ? 'mesaj' : 'mesaje' }}</p>

    <div class="admin-contact-list">
        @forelse($messages as $contactMessage)
            <article class="admin-contact-card">
                <header class="admin-contact-card-header">
                    <div>
                        <h2><a href="{{ route('admin.contact.show', $contactMessage) }}">{{ $contactMessage->subject }}</a></h2>
                        <p><strong>{{ $contactMessage->name }}</strong> · <a href="mailto:{{ $contactMessage->email }}">{{ $contactMessage->email }}</a></p>
                    </div>
                    <time datetime="{{ $contactMessage->created_at->toIso8601String() }}">{{ $contactMessage->created_at->format('d.m.Y, H:i') }}</time>
                </header>
                <p class="admin-contact-body">{{ $contactMessage->message }}</p>
                <footer><a class="admin-table-link" href="{{ route('admin.contact.show', $contactMessage) }}">Deschide mesajul <i class="bi bi-arrow-right" aria-hidden="true"></i></a></footer>
            </article>
        @empty
            <p class="admin-contact-empty">{{ $search ? 'Nu am găsit mesaje pentru această căutare.' : 'Nu au fost primite mesaje.' }}</p>
        @endforelse
    </div>

    <div class="admin-pagination">{{ $messages->links() }}</div>
</section>
@endsection
