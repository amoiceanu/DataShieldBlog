<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'Administrare — Securitate Digitală')</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/securitate-digitala-icon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/scss/app.scss', 'resources/scss/admin.scss', 'resources/js/app.js'])
</head>
<body class="admin-shell">
    <header class="admin-topbar">
        <a class="admin-brand" href="{{ route('admin.articole.index') }}"><i class="bi bi-shield-lock-fill"></i><span>Securitate Digitală <small>ADMIN</small></span></a>
        <div class="admin-actions">
            <nav class="admin-nav" aria-label="Administrare">
                <a class="{{ request()->routeIs('admin.articole.*') ? 'active' : '' }}" href="{{ route('admin.articole.index') }}">Articole</a>
                <a class="{{ request()->routeIs('admin.sectiuni.*') ? 'active' : '' }}" href="{{ route('admin.sectiuni.index') }}">Secțiuni</a>
            </nav>
            <span class="admin-user">{{ auth()->user()->name }}</span>
            <form action="{{ route('admin.logout') }}" method="post">@csrf<button class="admin-button" type="submit">Ieșire</button></form>
        </div>
    </header>
    <main class="admin-main">
        @if(session('success'))<div class="admin-success" role="status">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="admin-errors" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @yield('content')
    </main>
</body>
</html>
