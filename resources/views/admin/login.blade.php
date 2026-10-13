<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
    <title>Autentificare admin — Securitate Digitală</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/securitate-digitala-icon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/scss/app.scss', 'resources/scss/admin.scss', 'resources/js/app.js'])
</head>
<body class="admin-shell">
    <main class="admin-login-wrap"><section class="admin-login-card">
        <a class="admin-brand" href="{{ route('home') }}"><i class="bi bi-shield-lock-fill"></i><span>Securitate Digitală</span></a>
        <h1>Administrare</h1><p>Autentifică-te pentru a gestiona articolele și secțiunile.</p>
        @if($errors->any())<div class="admin-errors" role="alert">{{ $errors->first() }}</div>@endif
        <form class="admin-form" method="post" action="{{ route('admin.login.store') }}">
            @csrf
            <div class="admin-field"><label for="email">E-mail</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus></div>
            <div class="admin-field"><label for="password">Parolă</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
            <button class="admin-button primary" type="submit">Autentificare</button>
        </form>
    </section></main>
    <x-footer />
</body>
</html>
