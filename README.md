# SaldoUP

Base do SaaS de gestão financeira pessoal e familiar. Regras de produto em
`docs/PRODUCT.md` e `docs/FINANCIAL_RULES.md`.

## Desenvolvimento local

Requisitos: Docker Engine/Desktop com Compose e integração WSL habilitada.
Não é necessário instalar PHP, Composer ou Node no host.

```bash
cp .env.example .env
docker compose build app
docker compose run --rm --no-deps app composer install
docker compose run --rm --no-deps app php artisan key:generate
docker compose run --rm node npm ci
docker compose run --rm node npm run build
docker compose up -d
docker compose exec app php artisan migrate
```

Acesse http://localhost:8000. A porta fica restrita ao host; MySQL não expõe
porta externa. As senhas do Compose são exclusivamente para desenvolvimento.
Se seu usuário não tiver UID/GID 1000, configure `LOCAL_UID` e `LOCAL_GID`
no `.env` com os valores de `id -u` e `id -g`.

```bash
docker compose exec app php artisan test
docker compose exec app vendor/bin/pint --test
docker compose exec app composer audit
docker compose run --rm node npm audit
docker compose logs app
docker compose down
```

Os testes usam exclusivamente `saldoup_testing`; o banco é criado no primeiro
boot do volume MySQL. Em volumes existentes, aplique manualmente
`docker/mysql/init-testing.sql` com um usuário administrador antes dos testes.
Não execute testes contra banco de produção. `docker compose down` preserva
o volume de dados.

## Autenticação local

Cadastro em http://localhost:8000/cadastro e login em
http://localhost:8000/entrar, com e-mail e senha. A área inicial protegida fica
em `/painel`. Recuperação de senha em `/recuperar-senha` usa link com validade
de 60 minutos e uso único. O login por código de e-mail será implementado em
uma atualização futura, conforme `TASKS.md`.

Os e-mails locais ficam no Mailpit em http://localhost:8025; não são enviados
para destinatários externos. O `.env.example` já aponta o SMTP para `mailpit`
na porta 1025. Em instalações existentes, atualize essas variáveis no `.env`
e execute `php artisan config:clear` dentro do container. Não use o driver
`log` para registrar links ou códigos de acesso.

Senhas têm no mínimo 12 caracteres e no máximo 72 bytes para compatibilidade
com bcrypt. Há normalização do e-mail, hashing nativo, CSRF, limitação de
tentativas, renovação da sessão ao entrar e invalidação ao sair. Alterar a
senha invalida sessões antigas na próxima requisição protegida. Recuperação
retorna a mesma mensagem para e-mails existentes e desconhecidos. As páginas
de autenticação e da área pessoal usam `Cache-Control: no-store`.

## Escopo atual

Laravel, Blade, Livewire, Vite e ambiente PHP 8.4/MySQL 8.4; página pública
inicial, autenticação básica e verificações da aplicação e banco. Memberships,
assinaturas, dados financeiros, segundo fator e PWA ficam para incrementos posteriores.
Alpine será usado por meio do Livewire, evitando carregar duas instâncias.
Nenhuma regra financeira ou política de compartilhamento foi implementada.

## Publicação na KingHost

PHP 8.4 informado pelo responsável pelo projeto. Antes do deploy, validar no
plano contratado:

- Extensões Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PCRE,
  PDO/pdo_mysql, Session, Tokenizer e XML; BCMath para futuros cálculos e
  Intl conforme as funcionalidades utilizadas.
- Versão MySQL compatível e permissão para migrations; limites de recursos.
- HTTPS e document root apontando exclusivamente para `public/`.
- Escrita em `storage/` e `bootstrap/cache/`, sem permissões globais 777.
- Disponibilidade de cron, SSH e forma segura de executar comandos Laravel.

Prepare `composer install --no-dev --optimize-autoloader` e `npm ci && npm run
build` em ambiente local/CI separado. Publique código, `vendor/` e
`public/build/`, sem `.env`, `.git`, `node_modules`, testes ou documentação
interna. Configure os segredos diretamente no servidor, preserve `APP_KEY`,
use `APP_ENV=production`, `APP_DEBUG=false`, URL HTTPS e
`SESSION_SECURE_COOKIE=true`. Não use as credenciais locais em produção.
Configure também um provedor SMTP real e um remetente autorizado no servidor;
o Mailpit é exclusivo para desenvolvimento local.

Execute migrations somente após backup e avaliação da janela de mudança;
nunca `migrate:fresh`. Após configurar o ambiente, gere os caches com
`php artisan optimize`. Use sessão/cache em arquivo e fila síncrona nesta
base. Funcionalidades que dependam de cron devem permanecer desabilitadas
se o plano não o oferecer. Docker e servidor embutido PHP são apenas locais.

A publicação depende ainda de validação do plano, backups externos com teste
de restauração e decisões pendentes registradas nos documentos de produto.
