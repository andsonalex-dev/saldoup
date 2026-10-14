# PRODUCT.md — SaldoUP

## 1. Visão
SaldoUP é um SaaS PWA de organização financeira pessoal e familiar, com foco em visibilidade de contas, prevenção de desequilíbrio financeiro, planejamento e gestão de dívidas. O produto oferece informação e simulações, não garantia de resultado ou aconselhamento financeiro profissional individualizado.

## 2. Público e proposta
Pessoas que querem controlar receitas, despesas, cartões e dívidas; famílias que compartilham parte do orçamento e mantêm gastos privados; pessoas com remuneração fracionada e descontos em folha.

## 3. Identidade visual
- Nome: SaldoUP.
- Paleta: grafite predominante, roxo como destaque, cores semânticas acessíveis para alertas e resultados.
- Interface mobile-first, acessível, responsiva, clara e sem linguagem culpabilizadora.

## 4. Modelo SaaS e isolamento
- Uma assinatura/licença está vinculada a um **workspace financeiro**.
- Cada workspace pode ter um ou mais usuários membros; um usuário pode participar de mais de um workspace, com contexto ativo explícito.
- Cada usuário possui autenticação individual; não compartilhar credenciais.
- O plano determina quantidade máxima de membros e funcionalidades; valores, limites e cobrança ainda serão definidos.
- A licença possui status e validade; vencimento deve seguir política explícita de aviso, tolerância e acesso de leitura, ainda a definir. Não apagar dados automaticamente ao vencer.
- O administrador da plataforma gerencia assinaturas, licenças e usuários sem acesso rotineiro a lançamentos financeiros. Acesso excepcional de suporte requer autorização, trilha de auditoria e controles apropriados.

### Papéis do workspace (proposta inicial)
| Papel | Capacidades |
|---|---|
| Proprietário | Gerenciar membros, visibilidade, configurações e assinatura |
| Administrador familiar | Gerenciar finanças compartilhadas e convites, conforme permissões concedidas |
| Membro | Gerenciar seus registros privados e registros compartilhados autorizados |
| Leitor | Visualizar apenas dados explicitamente compartilhados |

- Papéis da plataforma são separados dos papéis do workspace.
- Entidades financeiras têm `workspace_id`, `created_by` e política de visibilidade (`shared` ou `private`) quando aplicável.
- Recursos privados são acessíveis ao dono e somente a quem receber autorização explícita; o proprietário do workspace não obtém automaticamente acesso a registros privados.
- Transferir propriedade, remover membros e alterar visibilidade exigem fluxos seguros e auditoria.
- Definir antes da implementação como lançamentos privados afetam indicadores familiares: padrão recomendado é excluir valores privados dos agregados compartilhados.

## 5. Funcionalidades por fase
### Fase 1 — MVP: base e controle
- Cadastro/login, recuperação de acesso, autenticação em dois fatores (método a confirmar), sessão segura.
- Administração da plataforma: ativação, licenças, papéis e bloqueios; sem leitura indiscriminada de dados financeiros.
- Workspace individual/familiar, convite e remoção de membros, permissões e visibilidade.
- Contas correntes, poupança, carteira em espécie e investimentos com saldo manual (sem atualização automática).
- Saldo inicial, lançamentos de receitas/despesas, categorias, recorrência e transferências internas.
- Cartões: múltiplos cartões, limites, fechamento/vencimento, compras parceladas, faturas e pagamentos.
- Dashboard: saldos, contas a pagar/receber, faturas e visão mensal explicável.
- PWA instalável, responsivo, assets estáticos offline e aviso de indisponibilidade para ações financeiras.

### Fase 2 — Planejamento e dívidas
- Cadastro de dívidas, inclusive quando valor original for desconhecido.
- Histórico de saldos informados e simulações de juros claramente rotuladas.
- Negociações, descontos, parcelas, calendário de obrigações e comparação de cenários.
- Previsão de caixa, comprometimento de renda e alertas por regras documentadas.
- Recebimentos fracionados, adiantamentos e décimo terceiro.

### Fase 3 — Automação e inteligência
- Importação de contracheque PDF com pré-visualização, confirmação e detecção de duplicidade.
- Descontos em folha, consignados e benefícios categorizados.
- Análises históricas, tendências, alertas avançados e cenários financeiros.
- Base de taxas externas versionada, com fonte, data e critérios de aplicação; somente após validação.

## 6. Fora do escopo inicial
Integração Open Finance, sincronização bancária automática, atualização automática de investimentos, OCR externo pago, IA como requisito obrigatório, pagamentos dentro da plataforma, operações offline com sincronização e aplicativo nativo.

## 7. Requisitos não funcionais
- PHP/Laravel e MySQL compatíveis com hospedagem compartilhada; sem processos residentes obrigatórios.
- Deploy de artefatos pré-compilados; HTTPS e `public/` como raiz web.
- Jobs agendados somente se houver cron; degradar graciosamente na ausência dele.
- Segurança multi-tenant, autorização por recurso, backups externos e logs mínimos.
- Performance: paginação, índices por `workspace_id`, evitar N+1 e limitar consultas de dashboard.
- Acessibilidade: navegação por teclado, contraste, labels e feedback de erros.

## 8. Modelo conceitual inicial (não é migration definitiva)
`users`, `workspaces`, `workspace_members`, `subscriptions`, `plans`, `accounts`, `categories`, `transactions`, `transfers`, `credit_cards`, `card_purchases`, `card_installments`, `card_statements`, `debts`, `debt_balances`, `debt_agreements`, `income_schedules`, `payslip_imports`, `audit_logs`.

A estrutura exata e os relacionamentos devem ser definidos por incrementos, com testes de autorização e regras financeiras.

## 9. Critérios de aceitação do MVP
- Dois usuários da mesma família conseguem entrar com logins individuais e acessar somente o que foi compartilhado.
- Usuário de outro workspace não consegue listar, consultar ou modificar registros alheios, mesmo manipulando IDs.
- Receitas, despesas, transferências e pagamentos de cartão não duplicam valores no saldo ou nos relatórios.
- Compras parceladas são distribuídas em faturas corretas segundo fechamento e vencimento configurados.
- Dashboard identifica origem dos valores e distingue previsto de efetivado.
- PWA pode ser instalado sob HTTPS e não armazena dados financeiros sensíveis em cache offline.
- Ambiente de produção funciona sem Node.js, Redis, Docker ou worker permanente.

## 10. Decisões pendentes de produto
1. Planos, preços, limite de membros e política de expiração/tolerância.
2. TOTP como 2FA padrão e política de exigência.
3. Escopo de visibilidade de dados privados nos indicadores familiares.
4. Política de exclusão, exportação e retenção de dados conforme análise LGPD.
5. Provedor de hospedagem e disponibilidade real de PHP, cron, extensões, SSH e configuração do document root.
