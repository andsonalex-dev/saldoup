# FINANCIAL_RULES.md — SaldoUP

## 1. Princípios gerais
- Distinguir fatos confirmados, lançamentos previstos e estimativas.
- Utilizar BRL inicialmente; valores monetários em `DECIMAL` e cálculos decimais precisos, nunca float.
- Definir política de arredondamento por operação, documentar precisão e testar centavos.
- Usar datas e fuso do workspace; armazenar timestamps de auditoria em padrão consistente.
- Nenhuma estimativa de dívida deve ser apresentada como saldo oficial do credor.
- Toda regra que afeta saldo deve ter testes de idempotência, duplicidade e autorização.

## 2. Contas e lançamentos
- Conta pode representar banco, poupança ou espécie. Investimentos têm atualização manual nesta fase.
- Saldo contábil = saldo inicial + entradas efetivadas - saídas efetivadas, respeitando estornos e ajustes.
- Lançamentos previstos não alteram saldo efetivado; alimentam previsão de caixa.
- Transferência entre contas próprias movimenta dois lados de uma mesma operação atômica e não é receita/despesa.
- Estornos preservam trilha de auditoria; evitar exclusão definitiva de operações financeiras.
- Compartilhamento e privacidade devem ser consistentes nos dois lados de uma transferência.

## 3. Receitas e despesas
- Tipos: receita, despesa, transferência e ajuste, com estados previsto, efetivado e cancelado conforme aplicável.
- Recorrência gera ocorrências identificáveis, evitando recriação duplicada.
- Categorias permitem relatórios, mas não alteram natureza contábil do lançamento.
- Valores negativos e reembolsos devem ter regras explícitas; não corrigir histórico silenciosamente.

## 4. Cartões de crédito
- Cadastrar limite total, data de fechamento e vencimento; permitir múltiplos cartões por workspace.
- Compra parcelada mantém valor total, quantidade de parcelas e valor de cada parcela; diferença de centavos deve ser alocada de forma determinística para que a soma feche exatamente.
- Atribuir parcelas à fatura conforme data da compra, fechamento e regras do cartão; não assumir que todos os emissores têm o mesmo comportamento.
- Distinguir fatura aberta, fechada, paga parcialmente, paga e vencida.
- Pagamento de fatura gera saída de caixa e baixa do passivo do cartão; não contabilizar novamente como despesa de consumo.
- Definir tratamento para estornos, juros, multas, pagamento parcial e rotativo antes de implementá-los.

## 5. Dívidas
- Registrar credor, natureza, datas, moeda, saldo atual informado, valor original opcional e documentação de origem.
- Se o valor original for desconhecido, não reconstruí-lo como fato; permitir somente estimativa identificada.
- Armazenar snapshots de saldos informados, com data e origem.
- Juros contratados têm prioridade sobre cenários hipotéticos; taxas médias de mercado servem apenas para comparação, com fonte e data.
- Fórmula de juros compostos em cenário simples: `M = P × (1 + i)^n`, onde `P` é principal, `i` é taxa efetiva por período e `n` é número de períodos; usar somente quando hipóteses contratuais forem compatíveis.
- Não aplicar automaticamente essa fórmula a dívidas com amortização, capitalização diferente, multas, encargos, impostos ou pagamentos intermediários.
- Se taxa anual e mensal precisarem ser convertidas, diferenciar taxa efetiva de nominal e declarar convenção.
- Informar que projeções não substituem demonstrativo atualizado do credor.

## 6. Negociações
- Registrar dívida vinculada, proposta, entrada, número e valor das parcelas, vencimentos, descontos, encargos e status.
- Comparar desembolso total, fluxo mensal, capacidade de pagamento e valor presente quando houver taxa de desconto fundamentada.
- Não afirmar que um acordo foi “bom negócio” apenas porque houve desconto nominal.
- Um acordo confirmado substitui a programação de pagamentos da dívida original, sem duplicar obrigações; preservar histórico.
- Negociação não confirmada é cenário e não altera fluxo de caixa oficial.

## 7. Recebimentos e folha
- Suportar salário, adiantamento, décimo terceiro, serviços, doações e outras receitas.
- Recebimento líquido = proventos - descontos, conforme rubricas confirmadas pelo usuário.
- Adiantamento salarial e pagamento complementar devem compor a remuneração do período sem dupla contagem.
- Suportar 5º dia útil com calendário configurável, considerando fins de semana e feriados aplicáveis; não presumir regras legais idênticas para todos os empregadores.
- Descontos de plano de saúde e consignados devem ser categorizados; evitar lançar consignado novamente como saída bancária se já deduzido em folha.
- Importação de PDF: extração → prévia → revisão humana → confirmação → persistência. Armazenar arquivo privado e detectar duplicidade por identificadores adequados.

## 8. Indicadores
- Fluxo de caixa projetado = saldo efetivado + entradas previstas - saídas previstas, no horizonte selecionado.
- Comprometimento da renda deve ter denominador explícito (preferencialmente renda líquida recorrente) e numerador configurado/documentado (obrigações e/ou dívidas).
- Não misturar gastos já realizados com obrigações futuras sem identificação.
- Alertas devem indicar fatores que os dispararam e não julgar o usuário.
- Limiares de alerta são parâmetros de produto a definir, não valores universais.
- Indicadores familiares agregam apenas registros compartilhados/autorizados, preservando privacidade individual.

## 9. Auditoria e integridade
- Toda modificação relevante deve registrar ator, workspace, entidade, ação e momento; limitar dados sensíveis nos logs.
- Proteger contra lançamentos duplicados com chaves de idempotência/constraints quando aplicável.
- Garantir consistência de operações compostas via transações de banco.
- Acesso a valores deve respeitar o papel do membro e a visibilidade do registro.

## 10. Casos mínimos de teste
1. Transferência de R$ 100 entre contas não altera patrimônio agregado.
2. Compra de R$ 100 em 3 parcelas soma exatamente R$ 100,00.
3. Pagamento de fatura não duplica despesa de consumo.
4. Adiantamento salarial não duplica renda mensal.
5. Consignado descontado em folha não é abatido novamente sem lançamento bancário real.
6. Negociação confirmada não mantém duas programações de pagamento ativas.
7. Dívida sem valor original permanece sem principal presumido.
8. Usuário sem autorização não acessa transações privadas de outro membro.
