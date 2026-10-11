@extends('admin.layout')
@section('title', $contactMessage->subject.' — Contact')
@section('content')
<div class="admin-heading">
    <div>
        <h1>{{ $contactMessage->subject }}</h1>
        <p>Mesaj primit prin formularul public de contact.</p>
    </div>
    <a class="admin-button" href="{{ route('admin.contact.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Înapoi la mesaje</a>
</div>

<article class="admin-panel admin-contact-detail">
    <dl class="admin-contact-meta">
        <div><dt>De la</dt><dd>{{ $contactMessage->name }}</dd></div>
        <div><dt>E-mail</dt><dd><a href="mailto:{{ $contactMessage->email }}">{{ $contactMessage->email }}</a></dd></div>
        <div><dt>Primit</dt><dd><time datetime="{{ $contactMessage->created_at->toIso8601String() }}">{{ $contactMessage->created_at->format('d.m.Y, H:i') }}</time></dd></div>
        <div><dt>Acord de confidențialitate</dt><dd>{{ $contactMessage->privacy_accepted_at?->format('d.m.Y, H:i') ?? '—' }}</dd></div>
    </dl>
    <hr>
    <h2>Mesaj</h2>
    <p class="admin-contact-body">{{ $contactMessage->message }}</p>
    <a class="admin-button primary" href="mailto:{{ $contactMessage->email }}?subject={{ rawurlencode('Re: '.$contactMessage->subject) }}"><i class="bi bi-reply" aria-hidden="true"></i> Răspunde prin e-mail</a>
</article>
@endsection
