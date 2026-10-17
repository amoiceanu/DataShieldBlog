@extends('layouts.app')
@section('title','Unde sunt expuse datele tale? — Securitate Digitală')
@push('head')<meta name="robots" content="noindex,nofollow">@endpush
@section('content')
<div class="container exposure-page">
 <section class="exposure-hero"><div><span class="eyebrow">PROTECȚIA DATELOR PERSONALE</span><h1>Unde sunt expuse<br><span>datele tale?</span></h1><p>Verifică dacă adresa ta de e-mail apare în breșe cunoscute și descoperă mențiuni publice ale numelui sau adresei tale.</p><div class="exposure-trust"><span><i class="bi bi-lock"></i> Rapoarte private</span><span><i class="bi bi-clock"></i> Retenție {{ $settings['retention_hours'] }} ore</span><span><i class="bi bi-shield-check"></i> Fără parole</span></div></div><div class="exposure-emblem" aria-hidden="true"><i class="bi bi-fingerprint"></i><span><i class="bi bi-shield-lock"></i></span></div></section>
 @if(session('exposure_notice'))<div class="exposure-notice" role="status">{{ session('exposure_notice') }}</div>@endif
 @if($errors->any())<div class="exposure-notice exposure-error" role="alert"><strong>Verifică formularul</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
 <div class="exposure-workspace" data-exposure-tabs>
 <nav class="exposure-tabs" aria-label="Tipul verificării">
 @if($settings['breaches'])<a href="#breaches" data-exposure-tab="breaches">E-mail în breșe de date</a>@endif
 @if($settings['mentions'])<a href="#mentions" data-exposure-tab="mentions">Apariții publice pe internet</a>@endif
 </nav>
 @if($settings['breaches'])
 <section id="breaches" class="exposure-panel" data-exposure-panel="breaches">
  <div class="exposure-panel-heading"><span class="exposure-icon"><i class="bi bi-envelope"></i></span><div><h2>Verifică adresa de e-mail</h2><p>Află dacă adresa ta apare în breșe de date cunoscute.</p></div><span class="exposure-source">Sursă: HIBP</span></div>
  @if(!$settingsService->configured('breaches') || !$settingsService->mailReady())<div class="exposure-notice"><i class="bi bi-info-circle"></i> Furnizor indisponibil: integrarea sau trimiterea confirmărilor nu este configurată. Verificarea nu a fost efectuată.</div>@endif
  <form method="post" action="{{ route('exposure.store') }}" class="exposure-form" data-processing-form>@csrf
   <input type="hidden" name="module" value="breaches"><input type="hidden" name="match_type" value="email">
   <label for="breachEmail">Adresa ta de e-mail</label><div class="exposure-input-row"><input id="breachEmail" name="subject" type="email" maxlength="150" autocomplete="email" placeholder="nume@exemplu.ro" required><button class="btn btn-primary" @disabled(!$settingsService->configured('breaches') || !$settingsService->mailReady())>Verifică adresa <i class="bi bi-arrow-right"></i></button></div>
   <label class="exposure-check"><input type="checkbox" name="own_data" value="1" required> <span>Verific propria adresă de e-mail.</span></label>
   <p class="exposure-small"><i class="bi bi-lock"></i> Îți trimitem un link unic, valabil 15 minute. Rezultatele sunt afișate doar după confirmarea adresei.</p>
   <p class="exposure-small">După confirmare, adresa va fi transmisă serviciului <a href="https://haveibeenpwned.com/" target="_blank" rel="noopener noreferrer">Have I Been Pwned</a> pentru verificare. Nu cerem și nu colectăm parole.</p>
  </form>
  <div class="exposure-steps"><div><b>01</b><strong>Confirmi adresa</strong><p>Deschizi linkul primit în același browser.</p></div><div><b>02</b><strong>Verificăm sursa</strong><p>Căutarea este procesată în siguranță, în fundal.</p></div><div><b>03</b><strong>Primești pași clari</strong><p>Vezi datele raportate și recomandările potrivite.</p></div></div>
 </section>@endif
 @if($settings['mentions'])
 <section id="mentions" class="exposure-panel" data-exposure-panel="mentions">
  <div class="exposure-panel-heading"><span class="exposure-icon"><i class="bi bi-globe2"></i></span><div><h2>Descoperă mențiuni publice</h2><p>Caută propriile date în surse publice, prin Brave Search.</p></div><span class="exposure-source">Sursă: Brave Search</span></div>
  @if(!$settingsService->configured('mentions'))<div class="exposure-notice">Furnizor indisponibil: Brave Search nu este configurat. Verificarea nu a fost efectuată.</div>@endif
  <form method="post" action="{{ route('exposure.store') }}" class="exposure-form" data-public-search data-processing-form>@csrf<input type="hidden" name="module" value="mentions">
   <fieldset class="exposure-choice"><legend>Ce vrei să verifici?</legend><label><input type="radio" name="match_type" value="name" checked> Numele meu</label><label><input type="radio" name="match_type" value="email"> Adresa mea de e-mail</label></fieldset>
   <label for="publicSubject" data-subject-label>Nume complet</label><input id="publicSubject" name="subject" maxlength="150" placeholder="Numele și prenumele" autocomplete="off" required>
   <div data-location><label for="publicLocation">Localitate sau țară <span class="exposure-small">(opțional)</span></label><input id="publicLocation" name="location" maxlength="100" placeholder="De exemplu, București" autocomplete="off"></div>
   <label class="exposure-check"><input type="checkbox" name="own_data" value="1" required><span data-own-label>Confirm că verific propriile date.</span></label>
   <p class="exposure-small">Numele și localitatea sau adresa de e-mail vor fi transmise Brave Search. Adresa de e-mail trebuie confirmată înainte de căutare; confirmarea din această sesiune poate fi refolosită timp de o oră.</p>
   <button class="btn btn-primary" @disabled(!$settingsService->configured('mentions'))>Caută mențiuni <i class="bi bi-arrow-right"></i></button>
  </form>
  <div class="exposure-notice">Rezultatele pot include persoane cu același nume. O mențiune publică nu reprezintă automat o scurgere de date.</div>
  <p class="exposure-small">Verificarea acoperă rezultatele disponibile prin furnizorul de căutare, nu toate paginile de pe internet. Folosim numai metadatele API; nu descărcăm paginile găsite și nu căutăm în conturi private.</p>
 </section>@endif
 @if(!$settings['breaches'] && !$settings['mentions'])<p class="exposure-panel">Modulele sunt momentan dezactivate. Verificarea nu a fost efectuată.</p>@endif
 </div>
 <section class="exposure-bottom"><div><i class="bi bi-shield-lock"></i><h2>Datele tale rămân ale tale</h2><p>Raportul este legat de această sesiune, criptat și disponibil cel mult {{ $settings['retention_hours'] }} ore. Îl poți șterge oricând. Nu publicăm căutările sau rezultatele.</p></div><div><i class="bi bi-eye"></i><h2>O verificare, nu o garanție</h2><p>Lipsa rezultatelor nu dovedește lipsa expunerii. Nu calculăm un scor de siguranță și nu promitem ștergerea automată a datelor de pe internet.</p></div></section>
 <p class="exposure-small exposure-retention">Ștergerea din aplicație nu elimină datele din sistemele furnizorilor. Copiile de siguranță expiră conform politicii operatorului. <a href="{{ route('privacy') }}">Politica de confidențialitate <i class="bi bi-arrow-up-right"></i></a></p>
</div>
@endsection
