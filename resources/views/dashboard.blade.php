<x-layout title="Minha área">
    <section aria-labelledby="page-title">
        <p class="eyebrow">Minha área</p>
        <h1 id="page-title" class="form-title">Olá, {{ auth()->user()->name }}.</h1>
        <p class="intro">Sua conta está pronta. Em breve você poderá organizar suas contas e lançamentos por aqui.</p>
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit">Sair da conta</button>
        </form>
    </section>
</x-layout>
