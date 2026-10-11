@extends('layouts.app')
@section('title', 'Politica de confidențialitate — Securitate Digitală')
@section('description', 'Informații despre datele prelucrate când folosești securitatedigitala.ro.')
@section('content')
<section class="page-hero container">
    <span class="eyebrow">TRANSPARENȚĂ</span>
    <h1>Politica de <span>confidențialitate</span></h1>
    <p>Informații despre datele prelucrate când folosești Securitate Digitală.</p>
</section>
<section class="container section prose-page">
    <p><strong>Notă:</strong> Acest text este un model informativ și nu reprezintă un document juridic definitiv. Adaptarea lui la situația operatorului și verificarea juridică sunt responsabilitatea acestuia.</p>
    <h2>Date colectate</h2>
    <p>Site-ul securitatedigitala.ro poate prelucra date tehnice strict necesare servirii paginilor. Dacă ne contactezi prin formular, colectăm numele, adresa de e-mail, subiectul și mesajul transmis, precum și momentul confirmării acordului.</p>
    <h2>Scop și temei</h2>
    <p>Folosim datele formularului pentru a citi și a răspunde solicitării tale, pe baza demersurilor cerute de tine și a interesului legitim de a gestiona comunicarea. Nu folosim datele pentru profilare sau publicitate.</p>
    <h2>Păstrare și securitate</h2>
    <p>Păstrăm mesajele atât cât este necesar pentru soluționarea solicitării și obligațiile aplicabile. Aplicăm măsuri rezonabile de acces limitat și securitate, însă nicio transmitere prin internet nu poate fi garantată complet.</p>
    <h2>Drepturile tale</h2>
    <p>În condițiile legii, poți solicita acces, rectificare, ștergere, restricționare sau opoziție și poți depune o plângere la autoritatea de supraveghere competentă. Pentru solicitări, folosește pagina <a href="{{ route('contact') }}">Contact</a>.</p>
    <h2>Servicii terțe</h2>
    <p>Fonturile, componentele vizuale sau infrastructura pot fi furnizate de terți și pot primi date tehnice precum adresa IP. Pentru utilizare în producție, verifică furnizorii configurați și actualizează acest text cu datele operatorului și contactul său.</p>
    <h2>Actualizări</h2>
    <p>Politica poate fi actualizată pentru a reflecta schimbări de funcționalitate sau cerințe legale. Verifică periodic această pagină.</p>
</section>
@endsection
