@extends('layouts.app')
@section('title', 'Contact — Securitate Digitală')
@section('description', 'Contactează Securitate Digitală pentru întrebări, sugestii sau corecturi.')
@section('content')
<section class="page-hero container">
    <span class="eyebrow">SUNTEM AICI</span>
    <h1>Hai să <span>vorbim.</span></h1>
    <p>Ai o întrebare, o sugestie sau ai observat ceva ce ar trebui să corectăm? Scrie-ne.</p>
</section>
<section class="container section contact-layout">
    <div class="contact-aside">
        <i class="bi bi-chat-square-text"></i>
        <h2>Mesajul tău contează.</h2>
        <p>Folosim datele din formular doar pentru a răspunde solicitării tale. Nu transmitem mesajele către servicii de analiză sau publicitate.</p>
        <a href="{{ route('privacy') }}" class="text-link">Politica de confidențialitate <i class="bi bi-arrow-up-right"></i></a>
    </div>
    <form class="contact-form" action="{{ route('contact.store') }}" method="post">
        @csrf
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        <div class="honeypot" aria-hidden="true"><label for="website">Lasă gol</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
        <div class="form-row">
            <label>Nume<input name="name" value="{{ old('name') }}" autocomplete="name" required>@error('name')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label>E-mail<input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>@error('email')<small class="field-error">{{ $message }}</small>@enderror</label>
        </div>
        <label>Subiect<input name="subject" value="{{ old('subject') }}" required>@error('subject')<small class="field-error">{{ $message }}</small>@enderror</label>
        <label>Mesaj<textarea name="message" rows="6" required>{{ old('message') }}</textarea>@error('message')<small class="field-error">{{ $message }}</small>@enderror</label>
        <label class="check-label"><input type="checkbox" name="privacy_accepted" value="1" @checked(old('privacy_accepted')) required><span>Sunt de acord cu prelucrarea datelor furnizate pentru a primi un răspuns la solicitarea mea. <a href="{{ route('privacy') }}">Detalii</a></span></label>
        @error('privacy_accepted')<small class="field-error">{{ $message }}</small>@enderror
        <button class="btn btn-primary" type="submit">Trimite mesajul <i class="bi bi-arrow-right"></i></button>
    </form>
</section>
@endsection
