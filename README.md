# DNS Panel RPZ

Painel para provedores de internet gerenciarem listas de bloqueio DNS (RPZ) e distribuí-las automaticamente para os servidores Unbound de seus clientes — sem agente instalado, sem SSH permanente e sem cron. A sincronização é feita nativamente pelo próprio Unbound, que puxa a zona via HTTP.

Produção: **https://rpz.trevizamnetwork.com.br**

Este é um projeto novo e separado do painel antigo (`dns-panel-central`, que gerencia provisionamento/tuning de servidores Unbound via SSH). Os dois não compartilham banco, código ou usuários.

## Como funciona a distribuição de listas

1. O admin (ou o próprio cliente, dentro do limite da licença) cadastra um **Servidor**, que recebe um **token** único gerado automaticamente.
2. O servidor Unbound do cliente é configurado com a cláusula `rpz:` (não `auth-zone:` — essa é a cláusula certa do Unbound para aplicar Response Policy Zones) apontando via `url:` para:
   ```
   https://rpz.trevizamnetwork.com.br/rpz/{token}.zone
   ```
3. O Unbound busca essa URL periodicamente. O painel responde com um zonefile RPZ válido: cabeçalho `SOA` com serial (timestamp Unix — cresce a cada geração, cabe em 32 bits), um domínio canário fixo (`blocktest.<host-do-painel>`) sempre presente para o cliente testar se a sincronização está funcionando, e uma linha `dominio CNAME .` por domínio ativo nas listas vinculadas àquele servidor.
4. A rota é pública (não exige login — o Unbound não tem sessão), mas exige token válido, servidor ativo **e empresa ativa**, e tem rate-limit (60 req/min por IP).
5. Validado com `named-checkzone` (pacote `bind9-utils`) — sintaticamente correto mesmo com dezenas de milhares de domínios.

## Entidades

- **Empresa** — o cliente (provedor de internet). Status: `pending` (recém-cadastrada, aguardando aprovação), `active`, `inactive`.
- **Licença** — vinculada a uma empresa, com `starts_at`/`expires_at` e `max_servidores`. Uma empresa pode ter várias; a capacidade de servidores é a soma das licenças ativas e vigentes.
- **Servidor** — pertence a uma empresa, tem token único (usado na URL do RPZ) e status.
- **Lista** — pode pertencer a uma empresa (lista privada) **ou não** (`empresa_id = null` = lista de catálogo, ex: lista da Anatel, reutilizável por qualquer empresa). Vinculada a servidores via tabela pivô `lista_servidor` (N:N).
- **Domínio** — pertence a uma lista, tem `dominio` + `ativo` (bool).
- **SugestaoDominio** — domínio sugerido por um cliente para bloqueio, com fluxo de aprovação pelo admin (aprovar escolhe em qual lista o domínio entra; rejeitar só marca o status).
- **User** — `role` (`admin` ou `cliente`) + `empresa_id` (só para clientes) + `avatar` (emoji opcional).

## Papéis e permissões

| Ação | Admin | Cliente |
|---|---|---|
| Ver/editar qualquer empresa | ✅ | ❌ (só a própria) |
| Criar/editar/remover empresa | ✅ | ❌ |
| Criar/editar licença | ✅ | ❌ |
| Criar/editar/remover servidor | ✅ (qualquer empresa) | ✅ (só a própria, até o limite da licença) |
| Vincular/desvincular lista a um servidor | ✅ | ✅ (nos próprios servidores; listas de catálogo + próprias) |
| Criar/editar/remover lista, gerenciar domínios | ✅ | ❌ (só sugestão) |
| Sugerir domínio | ✅ | ✅ |
| Aprovar/rejeitar sugestão | ✅ | ❌ |
| Ver dashboard | Global (todas as empresas) | Escopado à própria empresa |

Controle de acesso é feito via middleware `auth` (tudo exceto login/cadastro/RPZ) + `admin` (rotas restritas), mais checagem de propriedade (`empresa_id`) dentro dos controllers para as rotas que ambos os papéis acessam.

## Cadastro público e aprovação

`/cadastro` — a empresa se registra sozinha (nome, responsável, e-mail, senha). Fica com status `pending` e o usuário já é logado automaticamente, mas **não consegue criar servidor** até o admin:

1. Editar a empresa e trocar o status para `active`;
2. Criar uma licença para ela.

Sem licença ativa, o formulário de criar servidor mostra o motivo do bloqueio em vez de deixar salvar.

## Segurança implementada

- Autenticação via sessão (Laravel `Auth`), rate-limit de tentativas de login, senha com política de complexidade (maiúscula/minúscula/número/especial, mín. 8 caracteres).
- CSRF ativo em todas as rotas de escrita (confirmado testando requisição sem token → `419`).
- `APP_DEBUG=false` / `APP_ENV=production` — sem stack trace exposto.
- `.env`, `.git` e arquivos de config bloqueados via Nginx (fora da webroot / regra de negação de dotfiles).
- Cookie de sessão com nome neutro (`dns_panel_session`), `secure` (HTTPS), `httponly`, `samesite=lax`.
- `expose_php` desligado (não revela versão do PHP no header).
- Endpoint público do RPZ com rate-limit e checagem de empresa ativa (desativar uma empresa corta o serviço dos servidores dela).
- Todos os models usam `$fillable` explícito (sem mass assignment amplo).

## Infraestrutura (servidor `paineldns`, 45.239.157.239)

- Laravel 13 + SQLite (`database/database.sqlite`), PHP 8.4-FPM, Nginx.
- HTTPS via Let's Encrypt (`certbot --nginx`), renovação automática.
- Config real do Nginx e do timer de backup ficam em `/etc/nginx` e `/etc/systemd/system` — cópias de referência versionadas em [`deploy/`](deploy/) (ver `deploy/README.md`; **não são lidas automaticamente pelo servidor**, precisam ser copiadas manualmente se você editar a config real).
- Backup diário do SQLite via `systemd timer` (03:30, retém 14 dias) — script em `scripts/backup-db.sh`.
- Timezone da aplicação: `America/Sao_Paulo`.

## Rodando localmente

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan serve
```

Cadastre o primeiro admin diretamente via `php artisan tinker`:

```php
App\Models\User::create([
    'name' => 'Admin',
    'email' => 'admin@example.com',
    'password' => Hash::make('SenhaForte123!'),
    'role' => 'admin',
]);
```

## Estrutura de rotas

- `GET /`, `/empresas`, `/servidores`, `/listas`, `/licencas`, `/sugestoes` — CRUD padrão Laravel (`Route::resource`), com fatias `only`/`except` diferentes por papel.
- `GET|POST /login`, `GET|POST /cadastro` — públicas.
- `GET /rpz/{token}.zone` — pública, sem sessão, throttle 60/min.
- `POST /servidores/{servidor}/listas/{lista}/attach` e `DELETE .../detach` — vínculo servidor↔lista (self-service do cliente).
- `GET /perfil`, `PUT /perfil/avatar`, `GET|PUT /perfil/senha` — conta do usuário logado (qualquer papel).
- `POST /sugestoes/{sugestao}/aprovar|rejeitar` — admin only.

Lista completa: `php artisan route:list`.

## Identidade visual

CSS em `public/assets/app.css` — subconjunto **copiado literalmente** (não reimplementado) do `app.css` real do IRCENTER: tokens de cor/tema dark-light, shell (sidebar/topbar), `.panel`, `.data-table`, `.status-pill`, formulários, dropdown de conta (`.account-menu`), seletor de avatar (`.avatar-picker`). Cache-busting automático via `?v={mtime}` no `<link>` — não precisa de hard refresh depois de mudanças no CSS.

## Pendências conhecidas

- **Sem testes automatizados** — nenhuma cobertura ainda (PHPUnit configurado, mas vazio).
- **Notificação por Telegram** — decisão consciente de deixar por último; precisa de um bot token do BotFather.
- **RPZ**: SOA usa `localhost.` como MNAME/RNAME (placeholder) — pode ser trocado por um contato real do domínio.
