<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#17151e">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icons/icon.svg" type="image/svg+xml">
    <meta name="description" content="SaldoUP: organização financeira pessoal e familiar.">
    <title>SaldoUP — Sua vida financeira com clareza</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
    <main class="welcome">
        <p id="offline-notice" class="notice" role="status" hidden>Sem conexão. Reconecte-se para acessar suas finanças e registrar operações.</p>
        <button id="app-update" type="button" hidden>Atualizar aplicativo</button>
        <header><a class="brand" href="{{ route('home') }}" aria-label="SaldoUP, início">Saldo<span>UP</span></a></header>
        <section aria-labelledby="welcome-title">
            <p class="eyebrow">Organização financeira pessoal e familiar</p>
            <h1 id="welcome-title">Mais clareza para cuidar do seu dinheiro.</h1>
            <p class="intro">Um lugar para organizar contas, acompanhar despesas e planejar os próximos passos, no seu ritmo.</p>
            <nav class="form-links" aria-label="Acesso à conta">
                <a class="button" href="{{ route('register') }}">Criar conta</a>
                <a href="{{ route('login') }}">Já tenho uma conta</a>
            </nav>
        </section>
        <footer>Informações e simulações para apoiar sua organização financeira.</footer>
    </main>
    @livewireScripts
</body>
</html>
