# Pendências por fase — SaldoUP

Fonte funcional: `PRODUCT.md` e `FINANCIAL_RULES.md`. Este arquivo acompanha
o que falta implementar ou validar; não substitui as regras de domínio.
Itens ainda abertos não devem ser apresentados como concluídos.

## Fase 1 — Base e controle

### Administração e acesso

- [ ] Painel da plataforma para usuários, ativação, papéis e bloqueios,
  separado dos papéis familiares e sem acesso financeiro rotineiro.
- [ ] Comando seguro para criar administrador local, sem substituir a conta
  pessoal existente nem manter senha padrão no código.
- [ ] Painel familiar para configurar permissões por membro no workspace.
- [ ] Validar permissões no servidor em todas as consultas e mutações.
- [ ] Convites por e-mail com token protegido, expiração, uso único,
  validação do destinatário e proteção contra tentativas/reenvios.
- [ ] Aceitação, cancelamento e tratamento de convites duplicados.
- [ ] Remoção de membros com reautenticação e auditoria, sem apagar histórico.
- [ ] Transferência segura de propriedade com auditoria.
- [ ] Visibilidade privada/compartilhada e autorização explícita por recurso.
- [ ] Confirmar em testes que o proprietário não acessa automaticamente
  registros privados de outros membros.
- [ ] Testar localmente a lógica de convites e captura de e-mails.
- [ ] Validar entrega real de convites e recuperação após configurar SMTP.
- [ ] Definir e implementar política de segundo fator; login futuro por
  código de e-mail não equivale sozinho a dois fatores.

### Contas e lançamentos

- [ ] Contas correntes, poupança, espécie e investimentos com saldo manual.
- [ ] Saldo inicial, moeda BRL e valores DECIMAL com aritmética decimal segura.
- [ ] Documentar precisão, arredondamento e limites de entrada aprovados.
- [ ] Categorias e lançamentos de receita/despesa previstos e efetivados.
- [ ] Cancelamentos, ajustes, estornos e preservação da trilha de auditoria.
- [ ] Definir tratamento explícito de valores negativos e reembolsos.
- [ ] Transferências atômicas com privacidade consistente nos dois lados,
  sem classificá-las como receita/despesa ou alterar patrimônio agregado.
- [ ] Recorrência com ocorrências identificáveis e proteção contra duplicação.
- [ ] Idempotência, constraints, foreign keys e índices por workspace.
- [ ] Testes de centavos, duplicidades, autorização e isolamento por workspace.

### Cartões e dashboard

- [ ] Múltiplos cartões com limites, fechamento e vencimento configurados.
- [ ] Compras e parcelas cuja soma feche exatamente o valor total.
- [ ] Documentar atribuição de parcelas às faturas, dia de fechamento e
  tratamento de meses curtos conforme regras aprovadas.
- [ ] Faturas abertas, fechadas, parcialmente pagas, pagas e vencidas.
- [ ] Pagamentos com saída de caixa e baixa do passivo, sem duplicar consumo.
- [ ] Definir estornos, juros, multas, pagamento parcial e rotativo antes
  de implementar seus efeitos financeiros.
- [ ] Dashboard com saldos, contas a pagar/receber, faturas e visão mensal.
- [ ] Origem explicável dos totais e distinção entre previsto e efetivado.
- [ ] Agregados familiares excluem valores privados, conforme decisão confirmada.
- [ ] Testes de fechamento, parcelas e pagamentos sem dupla contagem.

### PWA e encerramento do MVP

- [ ] Manifest, ícones, instalação sob HTTPS e atualização controlada.
- [ ] Service worker com cache somente de assets estáticos e shell público.
- [ ] Aviso offline sem lançamentos financeiros offline ou sincronização.
- [ ] Testar ausência de dados financeiros, tokens, PDFs e páginas privadas
  em Cache Storage; manter respostas sensíveis com `no-store`.
- [ ] Validar instalação Android/desktop e documentar limitações do iOS.
- [ ] Revisar responsividade, teclado, contraste, labels e feedback de erros.
- [ ] Definir planos, preços, limites de membros e política de licença vencida.
- [ ] Implementar licenças, avisos, tolerância/leitura e bloqueios segundo
  decisões de produto; não apagar dados automaticamente por vencimento.
- [ ] Verificar todos os critérios de aceitação do MVP no `PRODUCT.md`.

Durante o desenvolvimento local, cobrança e bloqueios de licença estão
adiados por autorização do responsável. Autenticação atual por e-mail/senha
e base de workspaces já foram entregues; a Fase 1 ainda não está concluída.

## Fase 2 — Planejamento e dívidas

- [ ] Cadastro de dívidas com credor, natureza, datas, moeda, origem e
  saldo informado; valor original opcional, sem principal presumido.
- [ ] Histórico de snapshots com data e origem do saldo informado.
- [ ] Simulações claramente rotuladas, com hipóteses compatíveis com contrato.
- [ ] Convenções de taxas efetivas/nominais e conversões aprovadas e testadas.
- [ ] Não apresentar estimativa como saldo oficial do credor.
- [ ] Negociações com propostas, entradas, parcelas, vencimentos e status.
- [ ] Confirmação substitui a programação original sem duplicar obrigações.
- [ ] Comparação de desembolso total, fluxo e capacidade de pagamento;
  valor presente somente com taxa de desconto fundamentada.
- [ ] Previsão de caixa com horizonte e separação de previsto/efetivado.
- [ ] Comprometimento de renda com numerador e denominador documentados.
- [ ] Definir parâmetros de alertas e explicar fatores sem linguagem culpabilizadora.
- [ ] Recebimentos fracionados, adiantamentos e décimo terceiro sem dupla contagem.
- [ ] Calendário configurável para dias úteis e feriados aplicáveis.
- [ ] Testes de negociações, juros, calendários, privacidade e duplicidades.

## Fase 3 — Automação e inteligência

- [ ] Upload privado de contracheque PDF com limites, MIME e verificação de conteúdo.
- [ ] Extração, prévia, revisão humana e confirmação antes da persistência.
- [ ] Detecção de importações duplicadas e descarte seguro de arquivos.
- [ ] Proventos, descontos, consignados e benefícios categorizados.
- [ ] Não duplicar saída bancária de consignado já descontado em folha.
- [ ] Análises históricas, tendências, alertas avançados e cenários explicáveis.
- [ ] Taxas externas versionadas com fonte, data e critérios validados.
- [ ] Processamento em lotes, idempotência, retries e fallback apropriado;
  dependência de cron apenas quando disponível, sem worker permanente obrigatório.
- [ ] Testes de extração, confirmação, duplicidade, acesso a anexos e cenários.

## Antes da publicação — transversal

- [ ] Validar KingHost: PHP 8.4, extensões, MySQL, limites, SSH/cron e execução de comandos.
- [ ] Garantir HTTPS e document root exclusivo em `public/`.
- [ ] Configurar SMTP real, remetente autorizado e testar entrega.
- [ ] Publicar artefatos compilados sem `.env`, testes, `.git` ou `node_modules`.
- [ ] Proteger segredos, `APP_KEY`, cookies e sessão; desabilitar debug.
- [ ] Configurar backups criptografados externos e testar restauração.
- [ ] Definir retenção, exportação, exclusão, avisos e bases legais com apoio jurídico.
- [ ] Definir controle de acesso excepcional de suporte com autorização e auditoria.
- [ ] Auditar dependências PHP/JS e executar testes críticos antes da publicação.
- [ ] Configurar logs mínimos e monitoramento de erros, espaço, backups e certificado.
- [ ] Preparar janela/backup para migrations; nunca `migrate:fresh` em produção.

Validação local e validação no provedor devem ser registradas separadamente.
