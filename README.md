# DNS Panel RPZ

Painel para provedores de internet gerenciarem listas de bloqueio DNS (RPZ) e distribuí-las automaticamente para os servidores Unbound de seus clientes — sem agente instalado, sem SSH permanente e sem cron. A sincronização é feita nativamente pelo próprio Unbound, que puxa a zona via HTTP.

Produção: **https://rpz.trevizamnetwork.com.br**

Deu algum problema em produção? Veja o [`RUNBOOK.md`](RUNBOOK.md) — site fora do ar, RPZ não sincroniza, banco corrompido, disco cheio, certificado expirando, SSH travado, reverter deploy.

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
   - `redirect` — `CNAME rpz.trevizamnetwork.com.br.`, resolve para o próprio painel, que serve uma página de aviso ("Esta página está bloqueada") em vez de NXDOMAIN. Depende do vhost Nginx dedicado `dns-blocked-page` estar configurado como `default_server` (ver Infraestrutura). **Pendente desde a migração para o servidor novo (set/2026)**: o `default_server` da porta 443 está sendo usado por outro site (`ircenter`), não pelo `dns-blocked-page` — nenhum servidor está configurado com esse modo no momento, mas o primeiro que configurar vai ver a página errada até isso ser resolvido.
4. A rota é pública (não exige login — o Unbound não tem sessão), mas exige token válido, servidor ativo, **empresa ativa e licença ativa e vigente** (`Empresa::possuiLicencaAtiva()`), e tem rate-limit (60 req/min por IP). Sem licença ativa, a zona para de ser entregue mesmo que a empresa continue com status `active` — não é preciso um admin desativar a empresa manualmente quando a licença vence.
5. Validado com `named-checkzone` (pacote `bind9-utils`) — sintaticamente correto mesmo com dezenas de milhares de domínios.

### MikroTik RouterOS v7

Servidores cadastrados como **MikroTik RouterOS v7 (Adlist)** recebem um feed adicional em formato hosts:

```text
https://rpz.trevizamnetwork.com.br/mikrotik/{token-ou-slug}.hosts
```

Cada domínio ativo é entregue como `0.0.0.0 dominio.example`. O endpoint usa as mesmas listas, licença, ACL por IP, deduplicação e logs de sincronização do RPZ. No RouterOS, configure com `/ip dns adlist add url="..." ssl-verify=yes`. O Adlist não oferece a página de bloqueio do modo `redirect`; ele responde com `0.0.0.0`. Verifique a disponibilidade de `/ip dns adlist` e dimensione cache, RAM e armazenamento antes de carregar listas grandes.

## Listas externas (sincronização de qualquer feed de blacklist)

Além de listas manuais, o admin pode criar uma **Lista externa**: aponta uma URL (qualquer feed público ou privado de domínios maliciosos) e o painel sincroniza sozinho, periodicamente. Não é fixo num único provedor — dá pra apontar pra vários feeds diferentes, cada um sua própria lista.

- Ao criar/editar uma lista, escolha "Origem: Externa", informe a **URL do feed** e o **formato**:
  - `hostfile` — formato hosts file (`127.0.0.1 dominio.com` por linha), usado pelo [URLhaus](https://urlhaus.abuse.ch/) (abuse.ch) e outros feeds de threat intel.
  - `plain` — um domínio por linha, sem prefixo.
  - `unbound_local_zone` — formato nativo do Unbound (`local-zone: "dominio.com" redirect` + linhas `local-data`), lê só a linha `local-zone`.
- Comando: `php artisan external:sync` — roda **todas** as listas externas ativas de uma vez (idempotente, seguro rodar a qualquer hora). Uma lista com feed quebrado não impede as outras de sincronizarem.
- Domínios que não batem com a regex de FQDN válido (ex: entradas corrompidas por extração de PDF, URLs com path) são descartados silenciosamente na sincronização — não travam o processo nem entram errados no banco.
- Agendado via `systemd timer` a cada 6h (`dns-panel-rpz-external-sync.timer`) — não usa o scheduler do Laravel, mesmo padrão do backup. Também dá pra forçar na hora pelo botão "Sincronizar agora" na página da lista.
- Edição manual de domínios é bloqueada na UI e no controller pra listas externas (seria sobrescrita na próxima sync).
- Admin pode pausar/reativar a sincronização por lista (não some a lista, só para de atualizar) — botão em `/listas` ou na página da lista.
- **Proteção contra feed quebrado**: se um feed retornar menos de 100 domínios (sinal de formato mudado ou feed fora do ar), aquela lista específica é pulada sem ser alterada — evita esvaziar o bloqueio por engano. As outras listas continuam sincronizando normalmente.
- Como qualquer lista de catálogo (`empresa_id = null`), fica disponível pra qualquer empresa vincular a um servidor normalmente.
- Já vêm quatro listas pré-configuradas: [URLhaus](https://urlhaus.abuse.ch/) (malware/phishing ativo), [ThreatFox](https://threatfox.abuse.ch/) (C2/botnet), [Phishing Army](https://phishing.army/) (phishing) — as três gratuitas e sem chave de API — e **Anatel** (bloqueio judicial/regulatório), alimentada de duas formas complementares: um feed legado externo (pipeline próprio que extrai domínios dos PDFs, monta um `.txt` em formato `local-zone`, sobe via FTP — o painel só consome a URL a cada 6h) e upload manual de PDF/planilha direto no painel (ver seção "Importação ANATEL" abaixo). Pode editar a URL delas ou criar outras do zero. Testado com URLhaus+ThreatFox+Phishing Army somadas (~245k domínios, ~492k linhas no zonefile) em ~1,3s sem estourar memória.
- **Histórico de alterações** (`/listas/{id}/historico`, acessível a admin e cliente): mostra domínios adicionados/removidos num período (hoje / 7 dias / 30 dias), com data e hora de cada mudança. Em listas grandes (muitas mudanças no período), a tabela domínio-por-domínio fica escondida automaticamente — só o resumo numérico aparece, pra não travar a página com milhares de linhas. "Removido" aqui é desativado (`ativo=false`), não apagado do banco.
  - A mesma página tem um gráfico de barras (adicionados/removidos por dia, agregado via SQL — não sofre o limite de linhas da tabela detalhada), SVG inline sem biblioteca JS de gráfico, com tooltip nativo (`<title>`) ao passar o mouse na barra. Cores validadas com o `validate_palette.js` da skill `dataviz` (verde `#1f9d73` / âmbar `#b87b28` — tons mais escuros que os tokens de UI padrão `--green`/`--amber`, porque os originais são claros demais pra marca de gráfico em modo escuro).
- A lista **ANATEL principal** também incorpora, a cada `external:sync`, o feed legado configurado em `ANATEL_LEGACY_FEED_URL` (padrão: `https://trevizamnetwork.com.br/dns/lista_bloqueios.txt`). O arquivo pode continuar no formato antigo do Unbound (`local-zone`/`local-data`): o painel lê somente os domínios de `local-zone` e os converte internamente para o RPZ. Essa incorporação é aditiva: não remove domínios provenientes de PDFs e respeita as exclusões cadastradas na ANATEL.
- **Log do servidor** (página do servidor, `/servidores/{id}`): uma única tabela cronológica (últimos 30 dias, últimos 80 eventos), no mesmo espírito da página de Auditoria global mas escopada a este servidor — mistura dois tipos de evento numa linha do tempo só: quando o Unbound do cliente veio buscar a zona (quantos domínios foram entregues naquela busca, IP de origem) e quando uma lista vinculada ganhou/perdeu domínios. Junto, dá pra ver causa e efeito: a lista muda num dia, e a sincronização seguinte já reflete isso na contagem entregue. Cada evento de lista linka pro histórico completo dela. Isolado por servidor: só mostra sync e listas de fato vinculadas a ele.

## Importação ANATEL (PDF / Excel)

Além do feed legado citado acima, a lista ANATEL também é alimentada manualmente: um admin sobe os ofícios oficiais (PDF ou planilha `.xlsx`) em `/anatel`, o painel extrai os domínios e monta uma **prévia** — nada é aplicado à lista até alguém aprovar.

- **Formatos aceitos**: PDF e `.xlsx`. O tipo real do arquivo é validado pela assinatura binária (`%PDF-` ou ZIP com `xl/workbook.xml` dentro), nunca pela extensão que o navegador informou — um `.docx` renomeado pra `.xlsx`, por exemplo, é rejeitado. Até `ANATEL_MAX_FILES` arquivos por vez, `ANATEL_MAX_PDF_KB` cada.
- **Extração** (`scripts/anatel_pdf_extract.py`, roda num venv Python isolado dentro do container): pra PDF, varre tabelas e texto de cada página (`pdfplumber`); pra Excel, varre célula por célula com `openpyxl` (não assume layout fixo de coluna). Os dois usam o mesmo regex de detecção de domínio e viram um único fluxo de normalização (`App\Services\DomainNormalizer`) — minúsculas, remove `www.` (redundante: o zonefile já gera `*.dominio`, então `www.x` cai no bloqueio de `x` de qualquer jeito), valida formato de FQDN, suporta IDN.
- **Prévia antes de aplicar**: depois de extraído, cada domínio é classificado comparando com o que já está ativo na lista de destino — **Novo** (não existe ainda), **Existente** (já ativo, nada muda), **Reativado** (existia mas estava inativo) ou **Excluído** (bate com uma regra de exclusão manual da lista, ver `/listas/{id}/anatel/exclusions`). Como cada ofício da ANATEL costuma vir com a lista inteira atualizada (não só as novidades), a maioria de um arquivo novo normalmente já é "Existente" — só o que é de fato inédito aparece como "Novo".
- **Proteção contra extração incoerente**: se o arquivo tiver 100+ candidatos mas menos de `ANATEL_BLOCKED_RATIO` (padrão 10%) resultarem em domínios válidos, a importação é bloqueada automaticamente (status `blocked`) sem tocar na lista — sinal de PDF/planilha corrompido ou formato mudado.
- **Arquivo duplicado**: reenviar o mesmo arquivo (mesmo hash SHA-256) enquanto uma importação anterior dele ainda está pendente, processando ou já aplicada é rejeitado com mensagem clara — não deixa duplicar o trabalho nem a prévia.
- Ação final em `/anatel/imports/{id}/preview`: **Aprovar e atualizar lista** (aplica os domínios de verdade) ou **Não enviar ao RPZ** (rejeita, lista intocada). Dá pra excluir a prévia e o arquivo a qualquer momento antes de aprovar.
- Auditoria completa em `/auditoria` (quem subiu, quem aprovou/rejeitou, de qual IP) e histórico em `/listas/{id}/anatel/history`.

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

## Healthcheck (disco, certificado, disponibilidade)

- `php artisan health:check` roda a cada 30min via `systemd timer` (`dns-panel-rpz-healthcheck.timer`), como **root** no host — chama [`deploy/scripts/dns-panel-rpz-healthcheck-docker`](deploy/scripts/dns-panel-rpz-healthcheck-docker), que sobe um container descartável (`docker compose run`) montando o certificado do Let's Encrypt (`/etc/nginx/tls/...`) como somente-leitura.
- Verifica: uso de disco (alerta a partir de 85%), validade do certificado TLS (alerta a partir de 14 dias), e se `https://rpz.trevizamnetwork.com.br/up` responde 200.
- Quando está tudo OK, grava um `health.ok` silencioso (só pra saber "checou pela última vez há X min"). Quando encontra algo, grava um evento por problema (`health.disk_low`, `health.cert_expiring`, `health.site_down`, `health.cert_unreadable`) — aparece em `/seguranca` (card dedicado + alertas) e em `/auditoria`.
- Quando encontra um problema, também envia um alerta consolidado pelo Telegram configurado no painel. Falhas no Telegram não mascaram nem interrompem o healthcheck; os eventos continuam registrados na auditoria e em `/seguranca`.

## Notificação por Telegram

Quando alguém se cadastra pelo formulário público (`/cadastro`), o painel manda uma mensagem pra um grupo/tópico do Telegram (nome da empresa, responsável, e-mail) — pra saber na hora sem abrir o painel. A mesma integração envia alertas consolidados quando o healthcheck detecta problemas de disco, certificado ou disponibilidade. Se o envio falhar (bot mal configurado, Telegram fora do ar), o cadastro e o healthcheck **não são afetados**.

- Configurável 100% pela UI, sem precisar de SSH: `/configuracoes` (admin), painel "Notificação de cadastro via Telegram" — token do bot, chat ID, ID do tópico (se o grupo usar fóruns) e um toggle pra pausar sem perder a config. Tem botão "Enviar mensagem de teste".
- Guardado na tabela `settings` (chave/valor genérica, `App\Models\Setting`) — dá pra reaproveitar pra outras configurações futuras sem migration nova.
- Fallback pro `.env` (`TELEGRAM_BOT_TOKEN`, `TELEGRAM_CADASTROS_CHAT_ID`, `TELEGRAM_CADASTROS_THREAD_ID`) enquanto ninguém configurou nada pela UI — assim que salvar algo pela tela, o banco tem prioridade.
- Como achar o Chat ID e o ID do tópico: adicione o bot ao grupo, mande qualquer mensagem nele, acesse `https://api.telegram.org/bot<TOKEN>/getUpdates` no navegador — `chat.id` (negativo, pra grupos/supergrupos) e `message_thread_id` (se o grupo usa tópicos) aparecem na resposta.

## Infraestrutura (servidor `paineldns`)

Desde set/2026 a aplicação roda em **Docker** (`docker compose`, ver [`compose.yml`](compose.yml) e [`Dockerfile`](Dockerfile)). Só ficam direto no host: nginx de borda (TLS), Certbot, fail2ban e os timers systemd de healthcheck/renovação de certificado — tudo documentado com mais detalhe em [`deploy/README.md`](deploy/README.md) e no [`RUNBOOK.md`](RUNBOOK.md).

- Laravel 13 + SQLite (volume Docker `dns-panel-rpz-data`, `/data/database.sqlite` dentro do container), PHP 8.4-FPM (Alpine, container `app`), Nginx (container `nginx`, interno em `127.0.0.1:8082`, atrás de um Nginx de borda no host cuidando de TLS).
- Serviços: `app` (web/PHP-FPM), `nginx` (proxy interno), `queue` (`queue:work`, fila de extração de PDF/Excel ANATEL e progresso da UI), `scheduler` (`schedule:work`), `external-sync` (loop próprio, sincroniza listas externas a cada `AUTOMATION_INTERVAL`, padrão 6h), `backup` (loop próprio, backup diário do SQLite, retém 14 dias). Ver `compose.yml`.
- HTTPS via Let's Encrypt, emitido por um container `certbot/certbot` descartável (timer systemd `dns-panel-rpz-certbot-renew`, 2x/dia) e copiado/validado pro nginx de borda por [`deploy/scripts/dns-panel-rpz-deploy-certificate`](deploy/scripts/dns-panel-rpz-deploy-certificate) (com `nginx -t` + rollback automático se algo der errado).
- Config real do Nginx de borda e dos timers ficam em `/etc/nginx` e `/etc/systemd/system` — cópias de referência versionadas em [`deploy/`](deploy/) (ver `deploy/README.md`; **não são lidas automaticamente pelo servidor**, precisam ser copiadas manualmente se você editar a config real).
- Logs da aplicação/queue/scheduler/external-sync vão para `stdout`/`stderr` (`LOG_CHANNEL=stderr`), capturados pelo driver `json-file` do Docker com rotação embutida (`max-size 10m`, `max-file 3` por container) — não existe mais `storage/logs/*.log` nem `logrotate` pra isso. Ver com `docker compose logs -f <serviço>`.
- `dns-blocked-page` — app estático separado (`/opt/dns-blocked-page`, arquivos restaurados do backup da migração), pensado pra ser servido como `default_server` do Nginx de borda e exibir "Esta página está bloqueada" pra qualquer Host desconhecido (inclui o modo `redirect` do RPZ). **Não está ativo no servidor atual**: o `default_server` da porta 443 pertence a `ircenter-gateway.conf` (outro site, sem relação com o RPZ) — ver aviso em [`deploy/nginx/dns-blocked-page.conf`](deploy/nginx/dns-blocked-page.conf).
- Timezone da aplicação: `America/Sao_Paulo` (default do código em `config/app.php`, também setado explicitamente em `APP_TIMEZONE` no env de produção).
- `mem_limit` do container `app` em 768M (ver `compose.yml`) — a geração do zonefile RPZ consulta domínios via `DB::table()` puro (sem hidratar models Eloquent) de propósito, pra aguentar listas de dezenas de milhares de domínios (feeds de threat intel) sem estourar memória. Testado com 90k+ domínios reais e 20k num teste automatizado simulando 128M de limite.

## Rodando localmente

Pra desenvolvimento rápido na sua máquina, com o servidor embutido do PHP:

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

## Subindo em uma VM nova (staging ou uma segunda instância)

O projeto ainda não tem staging fixo rodando 24/7 — não compensa o custo/manutenção enquanto está em desenvolvimento, sem clientes reais dependendo dele. Em vez disso, o repositório privado no GitHub (`TrevizamNetwork001/dns-panel-rpz`) funciona como staging sob demanda: suba uma VM quando precisar testar algo mais a sério, derrube depois.

```bash
# na VM nova (Debian/Ubuntu, exemplo)
apt install -y php8.4-fpm php8.4-sqlite3 php8.4-mbstring php8.4-xml php8.4-curl composer nginx sqlite3 bind9-utils fail2ban

git clone https://github.com/TrevizamNetwork001/dns-panel-rpz.git
cd dns-panel-rpz
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate

# ajuste o .env: APP_ENV=staging, APP_DEBUG=false, APP_URL com o host/IP real da VM
```

Depois disso, siga o padrão do servidor de produção pra deixar realista:

- Vhost Nginx (adapte [`deploy/nginx/dns-panel-rpz-domain.conf`](deploy/nginx/dns-panel-rpz-domain.conf) trocando o `server_name`).
- Se quiser HTTPS de verdade, precisa de um subdomínio próprio (ex: `staging.rpz.trevizamnetwork.com.br`) apontando pro IP da VM — sem isso, o Certbot não emite certificado. Sem HTTPS também funciona pra teste, só perde a paridade com produção nesse ponto.
- `bind9-utils` é necessário pro teste que valida o zonefile com `named-checkzone` — sem ele, `php artisan test` falha um teste específico (mesma pegadinha que aconteceu no CI, ver `.github/workflows/tests.yml`).
- **Não** copie o `database.sqlite` de produção pra lá sem anonimizar antes (tem e-mail e dados reais de cliente) — comece com um banco vazio + o admin de teste do tinker acima.
- Timers/fail2ban de produção (`deploy/systemd/`, `deploy/fail2ban/`, `deploy/scripts/`) são opcionais numa VM de teste — copie só o que fizer sentido testar. Essa seção descreve um setup local sem Docker só pra teste rápido; produção roda em Docker (ver "Infraestrutura" acima).

## Testes automatizados

```bash
php artisan test
```

323 testes / 2.326 assertions cobrindo os pontos mais críticos:

- `tests/Feature/RpzZonefileTest.php` — geração do zonefile (token inválido, servidor/empresa inativos, domínio canário, modo `nxdomain` vs `redirect`, ACL de IP, criação de sync log, validação com `named-checkzone` de verdade, memória sob carga de 20k domínios).
- `tests/Feature/RegistrationTelegramTest.php`, `tests/Feature/ConfiguracoesTelegramTest.php` — notificação de cadastro via Telegram (payload correto, cadastro não quebra se o Telegram falhar ou não estiver configurado, tela de configuração admin-only, token preservado ao salvar sem preencher de novo, toggle de pausa).
- `tests/Feature/AuthTest.php` — login/logout, rate-limit de força bruta, log de falhas de autenticação.
- `tests/Feature/RoleAuthorizationTest.php` — isolamento admin vs cliente, inclusive entre empresas diferentes.
- `tests/Feature/ExternalListaSyncTest.php` — sincroniza múltiplas listas externas de uma vez, import, desativação de domínios que saíram do feed, feed quebrado não afeta as outras listas, pausa de sincronização, formatos `hostfile`/`plain`/`unbound_local_zone`, parsing de linhas inválidas/localhost.
- `tests/Feature/ListaCrudTest.php` — criação de lista manual e externa via HTTP real (POST), validação de URL obrigatória pra listas externas.
- `tests/Feature/ListaHistoryTest.php` — histórico de alterações por lista (acesso admin/cliente, domínios adicionados/removidos hoje, filtro de período, ocultação da tabela detalhada quando tem mudança demais).
- `tests/Feature/ServidorListaAtividadeTest.php` — atividade agregada das listas vinculadas a um servidor (isolamento entre listas vinculadas e não vinculadas, acesso cliente ao próprio servidor).
- `tests/Unit/ServidorIpMatchesCidrTest.php`, `tests/Unit/AuditLogBucketTest.php` — lógica pura (CIDR matching, classificação de severidade).
- `tests/Unit/DomainNormalizerTest.php` — normalização de domínio (URL/maiúsculas/ponto final, rejeita IP e lixo de PDF, remove prefixo `www.` redundante).

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
