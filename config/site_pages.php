<?php

return [
    'home' => ['label' => 'Pagina principală', 'route' => 'home', 'nav_label' => 'Home', 'kind' => 'Pagină'],
    'articles' => ['label' => 'Articole și pagini de articol', 'route' => 'articles.index', 'nav_label' => 'Articole', 'kind' => 'Modul'],
    'guides' => ['label' => 'Ghiduri', 'route' => 'articles.index', 'parameters' => ['categorie' => 'ghiduri'], 'nav_label' => 'Ghiduri', 'parent' => 'articles', 'kind' => 'Secțiune'],
    'about' => ['label' => 'Despre', 'route' => 'about', 'nav_label' => 'Despre', 'kind' => 'Pagină'],
    'contact' => ['label' => 'Contact și formularul de contact', 'route' => 'contact', 'nav_label' => 'Contact', 'kind' => 'Modul'],
    'privacy' => ['label' => 'Politica de confidențialitate', 'route' => 'privacy', 'nav_label' => 'Politica de confidențialitate', 'kind' => 'Pagină'],
    'cookies' => ['label' => 'Politica cookies', 'route' => 'cookies', 'nav_label' => 'Politica cookies', 'kind' => 'Pagină'],
    'exposure' => ['label' => 'Verificarea datelor personale', 'route' => 'exposure.index', 'nav_label' => 'Verifică datele', 'kind' => 'Modul'],
    'breaches' => ['label' => 'E-mail în breșe de date', 'parent' => 'exposure', 'kind' => 'Submodul'],
    'mentions' => ['label' => 'Apariții publice pe internet', 'parent' => 'exposure', 'kind' => 'Submodul'],
];
