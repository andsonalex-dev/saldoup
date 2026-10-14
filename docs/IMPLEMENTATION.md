# Implementação por fases — SaldoUP

Este acompanhamento não substitui `PRODUCT.md` nem `FINANCIAL_RULES.md`.
Os itens são concluídos por incrementos, com migrations aditivas e testes
locais. Não publicar enquanto as pendências de produção estiverem abertas.
Lista detalhada de pendências: `PENDING_BY_PHASE.md`.

## Fase 1 — MVP: base e controle (em andamento)

- [x] Laravel, Blade, Livewire/Alpine, Vite, PHP 8.4 e MySQL no Docker.
- [x] Cadastro, login por e-mail/senha, recuperação e sessão segura.
- [x] Criação, listagem e seleção explícita de workspaces financeiros.
- [x] Membership ativa verificada no servidor; edição de configurações
  somente pelo proprietário com membership ativa e papel de proprietário.
- [x] Auditoria mínima da criação e edição de workspaces e testes de isolamento.
- [x] Confirmar exclusão de valores privados dos agregados familiares.
- [ ] Convites, remoção de membros, permissões por recurso e autorização
  explícita de registros privados. Papéis já existem no banco; fluxos de
  gestão de membros ainda não estão disponíveis.
- [ ] Contas: corrente, poupança, espécie e investimentos de saldo manual.
- [ ] Saldo inicial, categorias, receitas/despesas previstas e efetivadas.
- [ ] Transferências atômicas, estornos, idempotência e recorrência.
- [ ] Cartões, distribuição de parcelas, fechamento, faturas e pagamentos.
- [ ] Dashboard financeiro com origem dos valores e previsto versus efetivado.
- [ ] PWA instalável com cache restrito a assets públicos e aviso offline.
- [ ] Administração da plataforma, planos, licenças, bloqueios e segundo fator.

Sequência de execução: concluir memberships e permissões, implementar contas
e lançamentos, depois transferências/recorrência, cartões, dashboard e PWA.
Administração comercial depende da definição dos planos e das regras de
licença. Segundo fator depende da confirmação de sua política.

Antes dos cálculos, documentar precisão/arredondamento e tratamentos ainda
pendentes nas regras financeiras. Antes das faturas, confirmar a atribuição
de compras na data de fechamento e o tratamento dos dias inexistentes em
meses curtos. Não presumir comportamentos universais dos emissores.

## Fase 2 — Planejamento e dívidas (pendente)

- [ ] Dívidas com valor original opcional e snapshots de saldo informado.
- [ ] Cenários de juros rotulados e compatíveis com hipóteses contratuais.
- [ ] Negociações e calendário sem duplicação de obrigações após confirmação.
- [ ] Previsão de caixa, comprometimento de renda e alertas explicáveis.
- [ ] Recebimentos fracionados, adiantamentos e décimo terceiro sem dupla contagem.

Dependências: estabilidade dos lançamentos e das permissões da Fase 1;
definição de denominadores, parâmetros de alerta, convenções de juros e
calendários aplicáveis antes de cada cálculo correspondente.

## Fase 3 — Automação e inteligência (pendente)

- [ ] Importação privada de contracheque PDF com prévia, revisão e deduplicação.
- [ ] Descontos em folha, consignados e benefícios categorizados.
- [ ] Histórico, tendências, alertas avançados e cenários.
- [ ] Base versionada de taxas externas com fontes, datas e critérios validados.

Dependências: segurança de anexos, retenção definida e confirmação humana
antes da persistência de dados extraídos. Automação assíncrona só poderá
depender de cron se sua disponibilidade estiver validada; sem workers
permanentes obrigatórios.

## Produção

Validação da KingHost, login futuro por código de e-mail, segundo fator,
retenção/exportação/exclusão e demais pendências em `../TASKS.md`.
Durante o desenvolvimento local não há cobrança ou bloqueios de licença;
esta condição não autoriza publicação sem controles comerciais definidos.
