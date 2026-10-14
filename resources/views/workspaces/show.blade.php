<x-layout title="Espaço financeiro">
    <section class="auth-panel" aria-labelledby="page-title">
        <h1 id="page-title" class="form-title">{{ $workspace->name }}</h1>
        <p class="intro">Fuso: {{ str_replace('_', ' ', $workspace->timezone) }}</p>
        <x-form-feedback />
        @if(app(\App\Services\Memberships\Permissions::class)->allows(auth()->user(),$workspace,'members.manage'))<nav class="form-links"><a href="{{ route('members.index',$workspace) }}">Membros e permissões</a></nav>@endif
        @can('update', $workspace)
            <h2>Configurações do espaço</h2>
            <form action="{{ route('workspaces.update', $workspace) }}" method="POST">
                @csrf
                @method('PATCH')
                <x-workspace-fields :workspace="$workspace" />
                <button type="submit">Salvar configurações</button>
            </form>
        @endcan
        <form action="{{ route('workspaces.activate', $workspace) }}" method="POST">
            @csrf
            <button type="submit">Usar este espaço</button>
        </form>
        <nav class="form-links" aria-label="Navegação"><a href="{{ route('workspaces.index') }}">Todos os meus espaços</a></nav>
    </section>
</x-layout>
