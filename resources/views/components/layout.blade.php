@props(['title'])
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#303039">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icons/icon.svg" type="image/svg+xml">
    <title>{{ $title }} — SaldoUP</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="{{ auth()->check() ? 'app-body' : 'public-body' }}">
    <a class="skip-link" href="#main-content">Ir para o conteúdo</a>
    @auth
        @php
            $navigationWorkspace = app(\App\Services\Memberships\ActiveWorkspace::class)->resolve(request());
            $navigationPermissions = $navigationWorkspace ? app(\App\Services\Memberships\Permissions::class)->snapshot(auth()->user(), $navigationWorkspace) : [];
        @endphp
        <header class="app-header">
            <a class="brand" href="{{ route('dashboard') }}" aria-label="SaldoUP, painel">Saldo<span>UP</span></a>
            <nav class="app-navigation" aria-label="Navegação principal">
                <a href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif>Painel</a>
                @if($navigationPermissions['accounts.view'] ?? false)<a href="{{ route('finance.index') }}" @if(request()->routeIs('finance.*')) aria-current="page" @endif>Contas e lançamentos</a>@endif
                @if($navigationPermissions['cards.view'] ?? false)<a href="{{ route('cards.index') }}" @if(request()->routeIs('cards.*')) aria-current="page" @endif>Cartões</a>@endif
                <a href="{{ route('workspaces.index') }}" @if(request()->routeIs('workspaces.*')) aria-current="page" @endif>Espaços</a>
                @if($navigationPermissions['members.manage'] ?? false)<a href="{{ route('members.index', $navigationWorkspace) }}" @if(request()->routeIs('members.*')) aria-current="page" @endif>Permissões</a>@endif
                @if(auth()->user()->is_platform_admin)<a href="{{ route('admin.users.index') }}" @if(request()->routeIs('admin.*')) aria-current="page" @endif>Administração</a>@endif
            </nav>
            <details class="user-menu"><summary>{{ auth()->user()->name }}</summary><div><p>{{ auth()->user()->email }}</p><form action="{{ route('logout') }}" method="POST">@csrf<button type="submit">Sair da conta</button></form></div></details>
        </header>
        <div class="context-bar"><span>{{ $title }}</span><a href="{{ route('workspaces.index') }}">{{ $navigationWorkspace?->name ?? 'Escolher espaço financeiro' }}</a></div>
    @endauth
    <main id="main-content" class="welcome" tabindex="-1">
        <p id="offline-notice" class="notice" role="status" hidden>Sem conexão. Reconecte-se para acessar suas finanças e registrar operações.</p>
        <button id="app-update" type="button" hidden>Atualizar aplicativo</button>
        @guest<header><a class="brand" href="{{ route('home') }}" aria-label="SaldoUP, início">Saldo<span>UP</span></a></header>@endguest
        {{ $slot }}
        <footer>Organização financeira pessoal e familiar.</footer>
    </main>
    @livewireScripts
</body>
</html>
