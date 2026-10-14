<x-layout title="Entrar">
    <section class="auth-panel" aria-labelledby="page-title">
        <h1 id="page-title" class="form-title">Bem-vindo de volta</h1>
        <p class="intro">Entre para acessar sua área pessoal.</p>
        <x-form-feedback />
        <form action="{{ route('login') }}" method="POST">
            @csrf
            <label for="email">E-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" maxlength="255" required autofocus @error('email') aria-invalid="true" aria-describedby="form-errors" @enderror>
            <label for="password">Senha</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required @error('password') aria-invalid="true" aria-describedby="form-errors" @enderror>
            <button type="submit">Entrar</button>
        </form>
        <nav class="form-links" aria-label="Opções de acesso">
            <a href="{{ route('password.request') }}">Esqueci minha senha</a>
            <a href="{{ route('register') }}">Criar minha conta</a>
        </nav>
    </section>
</x-layout>
