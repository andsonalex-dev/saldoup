<x-layout title="Visão geral">
    <section class="dashboard-panel" aria-labelledby="page-title">
        <div class="page-heading">
            <div><p class="eyebrow">Seu dinheiro, com clareza</p><h1 id="page-title" class="form-title">Olá, {{ auth()->user()->name }}.</h1><p class="field-help">Acompanhe a organização financeira do seu espaço.</p></div>
            @if($workspace && $allowed['transactions.create'])<a class="button" href="{{ route('finance.index') }}#new-transaction">+ Novo lançamento</a>@endif
        </div>
        <x-form-feedback />
        @if ($workspace)
            <div class="dashboard-layout">
                <div class="dashboard-content">
                    <div class="setup-card">
                        <div><span class="badge">{{ $workspace->name }}</span><h2>Suas finanças em um só lugar</h2><p>Organize suas contas, acompanhe os lançamentos e mantenha as permissões da família em dia.</p></div>
                        <nav class="setup-links" aria-label="Organizar espaço">
                            @if($allowed['accounts.view'])<a href="{{ route('finance.index') }}">Contas e lançamentos <span aria-hidden="true">→</span></a>@endif
                            @if($allowed['cards.view'])<a href="{{ route('cards.index') }}">Cartões e faturas <span aria-hidden="true">→</span></a>@endif
                            @if($allowed['members.manage'])<a href="{{ route('members.index', $workspace) }}">Membros e permissões <span aria-hidden="true">→</span></a>@endif
                            <a href="{{ route('workspaces.show', $workspace) }}">Configurações do espaço <span aria-hidden="true">→</span></a>
                        </nav>
                    </div>
                    <div class="period-heading"><h2>Visão mensal compartilhada</h2><form class="month-filter" method="GET" action="{{ route('dashboard') }}"><label for="dashboard-month">Mês</label><input id="dashboard-month" type="month" name="month" value="{{ $summary['month'] }}" required><button>Ver mês</button></form></div>
                    <div class="metrics-grid">
                        @foreach(['balance'=>['Saldo atual','Efetivado nas contas compartilhadas'], 'projected'=>['Saldo projetado','Saldo atual + previstos do mês'], 'income'=>['Receitas do mês','Efetivadas, líquidas de estornos'], 'expense'=>['Despesas do mês','Efetivadas, líquidas de estornos'], 'expected_income'=>['A receber','Receitas previstas no mês'], 'expected_expense'=>['A pagar','Despesas previstas no mês']] as $key=>$metric)
                            @if($summary[$key] !== null)<article class="metric-card {{ $key === 'balance' ? 'metric-featured' : '' }}"><h3>{{ $metric[0] }}</h3><p class="metric-value"><span>R$</span> {{ \App\Services\Finance\Money::format($summary[$key]) }}</p><p class="field-help">{{ $metric[1] }}</p></article>@endif
                        @endforeach
                    </div>
                    <div class="card-overview">
                        @foreach(['card_outstanding'=>['Faturas a pagar','Saldo ainda não pago das faturas com vencimento no mês'], 'card_purchases'=>['Compras no cartão','Total das compras realizadas no mês, na data da compra'], 'cash_card_payments'=>['Pagamentos de faturas','Saídas de caixa efetivadas no mês']] as $key=>$metric)
                            @if($summary[$key] !== null)<article class="metric-card"><h3>{{ $metric[0] }}</h3><p class="metric-value"><span>R$</span> {{ \App\Services\Finance\Money::format($summary[$key]) }}</p><p class="field-help">{{ $metric[1] }}</p></article>@endif
                        @endforeach
                    </div>
                </div>
                <aside class="dashboard-guide" aria-labelledby="guide-title">
                    <p class="eyebrow">Guia do painel</p><h2 id="guide-title">Entenda seus números</h2>
                    <div class="guide-item"><h3>Privacidade preservada</h3><p>Somente registros compartilhados autorizados. Contas e cartões privados ficam fora destes indicadores, mesmo que você tenha autorização para vê-los.</p></div>
                    <div class="guide-item"><h3>Saldo efetivado</h3><p>Saldo inicial + entradas efetivadas − saídas efetivadas. Transferências internas não são receitas ou despesas.</p></div>
                    <div class="guide-item"><h3>Previsão do mês</h3><p>Saldo atual mais receitas previstas menos despesas previstas no mês. Faturas pendentes são exibidas separadamente e ainda não integram automaticamente a previsão de caixa.</p></div>
                    <div class="guide-item"><h3>Compras e pagamentos</h3><p>Pagamentos de cartão não entram novamente nas despesas de consumo.</p></div>
                    <a class="guide-link" href="{{ route('workspaces.index') }}">Trocar espaço financeiro →</a>
                </aside>
            </div>
        @else
            <div class="setup-card"><div><h2>Vamos organizar suas finanças?</h2><p>Crie ou escolha seu espaço financeiro para começar.</p><a class="button" href="{{ route('workspaces.index') }}">Escolher espaço financeiro</a></div></div>
        @endif
    </section>
</x-layout>
