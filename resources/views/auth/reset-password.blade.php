<x-layout title="Redefinir senha">
    <section class="auth-panel" aria-labelledby="page-title">
        <h1 id="page-title" class="form-title">Escolha sua nova senha</h1>
        <x-form-feedback />
        <form action="{{ route('password.update') }}" method="POST">
            @csrf
            <input type="hidden" name="token" value="{{ request()->route('token') }}">
            <label for="email">E-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email', request()->query('email')) }}" autocomplete="username" maxlength="255" required @error('email') aria-invalid="true" aria-describedby="form-errors" @enderror>
            <label for="password">Nova senha</label>
            <p id="password-help" class="field-help">Use uma frase de pelo menos 12 caracteres.</p>
            <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required aria-describedby="password-help{{ $errors->has('password') ? ' form-errors' : '' }}" @error('password') aria-invalid="true" @enderror>
            <label for="password_confirmation">Confirme a nova senha</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required>
            <button type="submit">Redefinir senha</button>
        </form>
        <p class="form-links"><a href="{{ route('password.request') }}">Solicitar outro link</a></p>
    </section>
</x-layout>
