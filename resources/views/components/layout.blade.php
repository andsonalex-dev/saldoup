@props(['title'])
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#17151e">
    <title>{{ $title }} — SaldoUP</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
    <main class="welcome">
        <header><a class="brand" href="{{ route('home') }}" aria-label="SaldoUP, início">Saldo<span>UP</span></a></header>
        {{ $slot }}
        <footer>Organização financeira pessoal e familiar.</footer>
    </main>
    @livewireScripts
</body>
</html>
