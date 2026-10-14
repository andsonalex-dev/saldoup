<x-layout title="Espaços financeiros">
    <section class="workspace-panel" aria-labelledby="page-title">
        <p class="eyebrow">Organização pessoal e familiar</p>
        <h1 id="page-title" class="form-title">Seus espaços financeiros</h1>
        <p class="intro">Separe seus contextos financeiros e escolha em qual deseja trabalhar.</p>
        <x-form-feedback />
        <ul class="workspace-list" aria-label="Espaços disponíveis">
            @forelse ($workspaces as $workspace)
                <li class="workspace-card">
                    <h2>{{ $workspace->name }}</h2>
                    <p>Fuso: {{ str_replace('_', ' ', $workspace->timezone) }}</p>
                    @if (session('active_workspace_id') == $workspace->id)
                        <p class="eyebrow">Espaço ativo</p>
                    @endif
                    <div class="form-links">
                        <a href="{{ route('workspaces.show', $workspace) }}">Ver espaço</a>
                        <form action="{{ route('workspaces.activate', $workspace) }}" method="POST">
                            @csrf
                            <button type="submit">Usar este espaço</button>
                        </form>
                    </div>
                </li>
            @empty
                <li class="notice">Você ainda não tem um espaço financeiro. Crie o primeiro abaixo.</li>
            @endforelse
        </ul>
        {{ $workspaces->links() }}
        <div class="auth-panel">
            <h2 class="form-title">Criar espaço financeiro</h2>
            <form action="{{ route('workspaces.store') }}" method="POST">
                @csrf
                <x-workspace-fields />
                <button type="submit">Criar e usar este espaço</button>
            </form>
        </div>
        <nav class="form-links" aria-label="Navegação"><a href="{{ route('dashboard') }}">Voltar à minha área</a></nav>
    </section>
</x-layout>
