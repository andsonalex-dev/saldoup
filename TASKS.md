# Pendências — SaldoUP

## 1. Validar a hospedagem KingHost antes do deploy

Status: adiada por decisão do responsável; desenvolvimento e testes locais.
PHP alvo: 8.4. Não publicar até concluir esta validação.

- [ ] Confirmar extensões PHP listadas no README e requisitos do lockfile.
- [ ] Confirmar versão MySQL, acesso ao banco e execução segura de migrations.
- [ ] Confirmar HTTPS, document root em `public/` e permissões de escrita.
- [ ] Verificar SSH/cron, limites do plano e processo de publicação de artefatos.
- [ ] Configurar backups externos e testar restauração antes da produção.

## 2. Evoluir autenticação e definir privacidade

Decisão confirmada: e-mail e senha neste incremento; código por e-mail em
uma atualização futura. Código por e-mail para login não equivale, sozinho,
a autenticação de dois fatores.

- [x] Validar localmente cadastro, login, logout e recuperação de senha
  (testes Feature com MySQL isolado).
- [ ] Implementar futuramente login por código de e-mail, com expiração,
  uso único, proteção contra tentativas/reenvios e entrega segura.
- [ ] Definir se haverá segundo fator e sua política de exigência; TOTP
  permanece pendente nos documentos de produto.
- [x] Confirmar a política de agregação familiar: excluir valores privados
  dos totais compartilhados e exigir autorização explícita para acesso.
- [ ] Definir exportação, exclusão e retenção de dados antes da produção.

Esta lista complementa `docs/PRODUCT.md` e `docs/FINANCIAL_RULES.md`.
Não define cálculos financeiros nem concede acesso entre membros.
