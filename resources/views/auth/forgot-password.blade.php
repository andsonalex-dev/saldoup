<x-layout title="Recuperar senha">
    <section class="auth-panel" aria-labelledby="page-title">
        <h1 id="page-title" class="form-title">Recupere seu acesso</h1>
        <p class="intro">Informe seu e-mail para receber um link de redefinição de senha.</p>
        <x-form-feedback />
        <form action="{{ route('password.email') }}" method="POST">
            @csrf
            <label for="email">E-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="255" required autofocus @error('email') aria-invalid="true" aria-describedby="form-errors" @enderror>
            <button type="submit">Enviar link</button>
        </form>
        <p class="form-links"><a href="{{ route('login') }}">Voltar para entrar</a></p>
    </section>
</x-layout>
