@extends('layouts.app')
@section('title', 'Termeni și condiții — Securitate Digitală')
@section('description', 'Termenii de utilizare pentru site-ul și materialele educative Securitate Digitală.')
@section('content')
<section class="page-hero container">
    <span class="eyebrow">INFORMAȚII LEGALE</span>
    <h1>Termeni și <span>condiții</span></h1>
    <p>Reguli de folosire a site-ului și a materialelor publicate de Securitate Digitală.</p>
</section>
<section class="container section prose-page">
    <p><strong>Ultima actualizare:</strong> {{ now()->format('d.m.Y') }}</p>
    <p>Securitate Digitală este un proiect educațional cu informații și recomandări generale despre siguranța online. Acești termeni descriu condițiile generale de utilizare a site-ului.</p>

    <h2>1. Scopul materialelor</h2>
    <p>Articolele, ghidurile și sfaturile au scop informativ și educativ. Ele nu reprezintă consultanță juridică, financiară sau de securitate informatică adaptată unei persoane ori organizații și nu înlocuiesc evaluarea unui specialist. Situațiile și recomandările pot varia în funcție de dispozitiv, cont, furnizor și context.</p>
    <p>Depunem eforturi pentru a prezenta informații clare și actuale, dar produsele și amenințările se schimbă. Verifică recomandările și instrucțiunile în documentația oficială a furnizorului înainte să modifici setări, să instalezi programe sau să iei o decizie importantă.</p>

    <h2>2. Utilizarea site-ului</h2>
    <p>Poți consulta și distribui linkuri către paginile publice pentru uz personal și informativ. Nu folosi site-ul pentru activități ilegale, pentru a perturba funcționarea lui, pentru a încerca accesul neautorizat la sisteme sau pentru a colecta automat date într-un mod care afectează serviciul ori drepturile altor persoane.</p>
    <p>Nu trimite prin formularul de contact parole, coduri de autentificare, date complete de card, copii de acte sau alte informații sensibile de care nu este nevoie pentru solicitarea ta.</p>

    <h2>3. Drepturi asupra conținutului</h2>
    <p>Textele, identitatea vizuală și celelalte materiale proprii publicate pe securitatedigitala.ro sunt protejate de drepturile aplicabile. Poți cita fragmente scurte în scop informativ, cu atribuirea clară a sursei și un link către pagina originală. Pentru republicarea integrală ori utilizarea comercială a materialelor, solicită acordul prin pagina <a href="{{ route('contact') }}">Contact</a>.</p>
    <p>Unele pagini trimit către surse sau servicii externe. Acestea sunt oferite pentru referință; nu controlăm conținutul, disponibilitatea sau politicile site-urilor terțe.</p>

    <h2>4. Disponibilitatea și modificările</h2>
    <p>Putem actualiza, muta sau retrage pagini pentru a menține informațiile relevante și site-ul funcțional. Nu garantăm disponibilitatea neîntreruptă a site-ului sau că fiecare material va rămâne neschimbat. Nicio prevedere din acești termeni nu limitează drepturile care nu pot fi excluse potrivit normelor aplicabile.</p>

    <h2>5. Date personale și cookie-uri</h2>
    <p>Detaliile despre datele prelucrate prin formular și despre tehnologiile folosite pe site sunt descrise în <a href="{{ route('privacy') }}">Politica de confidențialitate</a> și <a href="{{ route('cookies') }}">Politica cookies</a>.</p>

    <h2>6. Contact și informații despre proiect</h2>
    <p>Pentru întrebări despre conținut sau utilizarea site-ului, folosește pagina <a href="{{ route('contact') }}">Contact</a>. Securitate Digitală este un proiect educațional, iar formularul de contact este canalul disponibil pe acest site pentru solicitări.</p>

    <h2>7. Actualizarea termenilor</h2>
    <p>Putem revizui acești termeni atunci când se schimbă site-ul sau modul în care funcționează. Versiunea curentă va fi publicată pe această pagină împreună cu data actualizării.</p>
</section>
@endsection
