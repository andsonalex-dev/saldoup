<x-layout title="Criar conta">
    <section class="auth-panel" aria-labelledby="page-title">
        <h1 id="page-title" class="form-title">Crie sua conta</h1>
        <p class="intro">Comece sua organização financeira.</p>
        <x-form-feedback />
        <form action="{{ route('register') }}" method="POST">
            @csrf
            <label for="name">Nome</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" maxlength="255" required autofocus @error('name') aria-invalid="true" aria-describedby="form-errors" @enderror>
            <label for="email">E-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" maxlength="255" required @error('email') aria-invalid="true" aria-describedby="form-errors" @enderror>
            <label for="password">Senha</label>
            <p id="password-help" class="field-help">Use uma frase de pelo menos 12 caracteres.</p>
            <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required aria-describedby="password-help{{ $errors->has('password') ? ' form-errors' : '' }}" @error('password') aria-invalid="true" @enderror>
            <label for="password_confirmation">Confirme a senha</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required>
            <button type="submit">Criar conta</button>
        </form>
        <p class="form-links">Já tem uma conta? <a href="{{ route('login') }}">Entrar</a></p>
    </section>
</x-layout>
