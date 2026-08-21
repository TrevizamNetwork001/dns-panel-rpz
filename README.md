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
   O bloco `rpz:` precisa ficar **depois** do fim do bloco `server:` no `unbound.conf` (antes dele, se você usar `hyperlocal`). Rode `unbound-checkconf` antes de reiniciar o serviço, para garantir que a config está válida e o Unbound não caia no reload.
3. O Unbound busca essa URL periodicamente. O painel responde com um zonefile RPZ válido: cabeçalho `SOA` com serial (timestamp Unix — cresce a cada geração, cabe em 32 bits), um domínio canário fixo (`blocktest.<host-do-painel>`, sempre `CNAME .`, usado para o cliente testar se a sincronização está funcionando) e uma linha `dominio CNAME <alvo>` + `*.dominio CNAME <alvo>` por domínio ativo nas listas vinculadas àquele servidor. O `<alvo>` depende do **modo de bloqueio** configurado em cada servidor:
   - `nxdomain` (padrão) — `CNAME .`, o domínio parece inexistente.
   - `redirect` — `CNAME rpz.trevizamnetwork.com.br.`, resolve para o próprio painel, que serve uma página de aviso ("Esta página está bloqueada") em vez de NXDOMAIN. Depende do vhost Nginx dedicado `dns-blocked-page` estar configurado como `default_server` (ver Infraestrutura).
4. A rota é pública (não exige login — o Unbound não tem sessão), mas exige token válido, servidor ativo **e empresa ativa**, e tem rate-limit (60 req/min por IP).
5. Validado com `named-checkzone` (pacote `bind9-utils`) — sintaticamente correto mesmo com dezenas de milhares de domínios.

## Listas externas (feeds públicos de blacklist)

Além de listas manuais, o painel sincroniza automaticamente uma lista de **domínios maliciosos** a partir do feed público [URLhaus](https://urlhaus.abuse.ch/) (abuse.ch) — malware/phishing ativo, gratuito, sem chave de API.

- Comando: `php artisan urlhaus:sync` (idempotente, seguro rodar a qualquer hora).
- Agendado via `systemd timer` a cada 6h (`dns-panel-rpz-urlhaus.timer`) — não usa o scheduler do Laravel, mesmo padrão do backup.
- A lista fica marcada como `origem = externa`, `fonte_externa = urlhaus`. Edição manual de domínios é bloqueada na UI e no controller (seria sobrescrita na próxima sync).
- Admin pode pausar/reativar a sincronização (não some a lista, só para de atualizar) — botão "Pausar sync" em `/listas` ou na página da lista.
- **Proteção contra feed quebrado**: se o URLhaus retornar menos de 100 domínios (sinal de formato mudado ou feed fora do ar), o comando aborta sem alterar a lista — evita esvaziar o bloqueio por engano.
- Como qualquer lista de catálogo (`empresa_id = null`), fica disponível pra qualquer empresa vincular a um servidor normalmente.

## Entidades

- **Empresa** — o cliente (provedor de internet). Status: `pending` (recém-cadastrada, aguardando aprovação), `active`, `inactive`.
- **Licença** — vinculada a uma empresa, com `starts_at`/`expires_at` e `max_servidores`. Uma empresa pode ter várias; a capacidade de servidores é a soma das licenças ativas e vigentes.
- **Servidor** — pertence a uma empresa, tem token único (usado na URL do RPZ), status e `bloqueio_modo` (`nxdomain` ou `redirect`).
- **Lista** — pode pertencer a uma empresa (lista privada) **ou não** (`empresa_id = null` = lista de catálogo, ex: lista da Anatel, reutilizável por qualquer empresa). Vinculada a servidores via tabela pivô `lista_servidor` (N:N). Pode ser `manual` ou `externa` (sincronizada de um feed público — ver seção acima).
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
| Pausar/reativar sincronização de lista externa | ✅ | ❌ |
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

## Segurança do host (SSH / fail2ban)

- `fail2ban` monitora o SSH via journal (`backend = systemd`): 5 tentativas de senha erradas em 10 minutos → IP banido por 1h. Config em `/etc/fail2ban/jail.local` (versionada em `deploy/fail2ban/`).
- Cada ban/unban dispara `php artisan security:log-ban {ip} {jail} {ban|unban}` (hook customizado em `deploy/fail2ban/dns-panel-hook.conf`), que grava em `security_bans` **e** no `audit_logs` normal — aparece tanto em `/seguranca` quanto em `/auditoria`.
- Página `/seguranca` (admin) mostra IPs banidos agora, histórico de bans/unbans e falhas de login no painel — visão consolidada de ameaças.
- **Pendente de decisão consciente**: `PermitRootLogin yes` e `PasswordAuthentication yes` continuam ativos no `sshd_config`. Já existe uma chave SSH (ed25519) instalada em `~/.ssh/authorized_keys` do root e testada com sucesso (login sem senha funciona), mas a decisão do dono do servidor foi manter login por senha habilitado por enquanto — a chave fica como opção extra, não obrigatória. Quando quiser travar (recomendado): mudar `PermitRootLogin` para `prohibit-password` e `PasswordAuthentication` para `no`, testar login por chave numa sessão nova **antes** de fechar a sessão atual.

## Infraestrutura (servidor `paineldns`, 45.239.157.239)

- Laravel 13 + SQLite (`database/database.sqlite`), PHP 8.4-FPM, Nginx.
- HTTPS via Let's Encrypt (`certbot --nginx`), renovação automática.
- Config real do Nginx e dos timers ficam em `/etc/nginx` e `/etc/systemd/system` — cópias de referência versionadas em [`deploy/`](deploy/) (ver `deploy/README.md`; **não são lidas automaticamente pelo servidor**, precisam ser copiadas manualmente se você editar a config real).
- Backup diário do SQLite via `systemd timer` (03:30, retém 14 dias) — script em `scripts/backup-db.sh`.
- Sync da lista URLhaus via `systemd timer` a cada 6h — script em `scripts/sync-urlhaus.sh`.
- `dns-blocked-page` — app estático separado (`/opt/dns-blocked-page`) servido como `default_server` do Nginx, exibe a página "Esta página está bloqueada" para qualquer Host desconhecido (inclui o modo `redirect` do RPZ). O painel antigo (`dns-panel-central`) e este painel continuam com seus próprios vhosts nominais — só o catch-all mudou de dono.
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

## Testes automatizados

```bash
php artisan test
```

45 testes / 85 assertions cobrindo os pontos mais críticos:

- `tests/Feature/RpzZonefileTest.php` — geração do zonefile (token inválido, servidor/empresa inativos, domínio canário, modo `nxdomain` vs `redirect`, ACL de IP, criação de sync log, validação com `named-checkzone` de verdade).
- `tests/Feature/AuthTest.php` — login/logout, rate-limit de força bruta, log de falhas de autenticação.
- `tests/Feature/RoleAuthorizationTest.php` — isolamento admin vs cliente, inclusive entre empresas diferentes.
- `tests/Feature/UrlhausSyncTest.php` — import, desativação de domínios que saíram do feed, proteção contra feed quebrado (< 100 domínios), pausa de sincronização, parsing de linhas inválidas/localhost.
- `tests/Unit/ServidorIpMatchesCidrTest.php`, `tests/Unit/AuditLogBucketTest.php` — lógica pura (CIDR matching, classificação de severidade).

Usa banco SQLite em memória (`phpunit.xml`, `DB_DATABASE=:memory:`) — não toca no banco real. `Http::fake()` mockado nos testes que envolvem chamada externa (URLhaus).

## CI

- Repositório privado no GitHub: `github.com/TrevizamNetwork001/dns-panel-rpz` (só espelha o código — deploy continua manual via SSH, não é automático a partir daqui).
- `.github/workflows/tests.yml` roda a suite completa (`php artisan test`) a cada push/PR em qualquer branch. Instala PHP 8.4, `bind9-utils` (pro teste que valida o zonefile com `named-checkzone`) e sobrescreve `APP_URL` no `.env` de teste (senão o host do painel vira `localhost`, que colide com o NS fixo do RPZ e quebra a validação de zona).
- Hook local (`.git/hooks/pre-commit`, cópia em `deploy/git-hooks/`) roda a mesma suite antes de cada commit local — dupla camada, tanto local quanto no GitHub.

## Estrutura de rotas

- `GET /`, `/empresas`, `/servidores`, `/listas`, `/licencas`, `/sugestoes` — CRUD padrão Laravel (`Route::resource`), com fatias `only`/`except` diferentes por papel.
- `GET|POST /login`, `GET|POST /cadastro` — públicas.
- `GET /rpz/{token}.zone` — pública, sem sessão, throttle 60/min.
- `POST /servidores/{servidor}/listas/{lista}/attach` e `DELETE .../detach` — vínculo servidor↔lista (self-service do cliente).
- `PATCH /listas/{lista}/toggle-sync` — admin only, pausa/reativa sync de lista externa.
- `GET /perfil`, `PUT /perfil/avatar`, `GET|PUT /perfil/senha` — conta do usuário logado (qualquer papel).
- `POST /sugestoes/{sugestao}/aprovar|rejeitar` — admin only.

Lista completa: `php artisan route:list`.

## Identidade visual

CSS em `public/assets/app.css` — subconjunto **copiado literalmente** (não reimplementado) do `app.css` real do IRCENTER: tokens de cor/tema dark-light, shell (sidebar/topbar), `.panel`, `.data-table`, `.status-pill`, formulários, dropdown de conta (`.account-menu`), seletor de avatar (`.avatar-picker`). Cache-busting automático via `?v={mtime}` no `<link>` — não precisa de hard refresh depois de mudanças no CSS.

## Pendências conhecidas

- **Notificação por Telegram** — decisão consciente de deixar por último; precisa de um bot token do BotFather.
- **RPZ**: SOA usa `localhost.` como MNAME/RNAME (placeholder) — pode ser trocado por um contato real do domínio.
