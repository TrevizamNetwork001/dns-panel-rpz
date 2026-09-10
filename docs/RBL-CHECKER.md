# RBL Checker — RBL-6

## Delist assistido — RBL-6

O detalhe de cada evento em `/rbl/events/{event}` possui a seção **Delist assistido**.
Ela mostra o IP afetado, lista, resposta DNSBL, datas, grupo, orientação cadastrada,
links de consulta/solicitação e um texto base editável para o operador copiar. Links
e instruções pertencem à configuração de cada RBL e devem ser revisados pelo
administrador, pois Spamhaus, SpamCop, Barracuda e SORBS mantêm fluxos e políticas
próprios que podem mudar.

O operador pode registrar instruções visualizadas, solicitação, espera, aceite,
rejeição, não aplicabilidade ou cancelamento, além de data, protocolo, URL usada,
e-mail de contato e observações. O histórico identifica o responsável. Observações
não são copiadas para a descrição do `AuditLog`; a auditoria registra somente o
evento e o status operacional. `/rbl/events` mostra o último status, o relatório do
evento traz orientação e histórico resumido, `/rbl/reports` resume os principais
estados e o CSV inclui `delist_status`, `delist_requested_at` e `delist_protocol`
com a mesma neutralização contra fórmulas das demais colunas.

Delist assistido não é delist automático: o painel não envia formulário, e-mail ou
pedido/API externa, não faz login em portal, não burla CAPTCHA e não executa script.
Também não altera firewall, não bloqueia cliente, não integra MikroTik, não importa
logs NAT/CGNAT e não altera checks DNSBL nem o fluxo RPZ. Nenhuma mudança de delist
envia Telegram. Um aceite operacional não altera `status` ou `resolved_at`; o evento
só é resolvido tecnicamente quando uma consulta DNSBL normal voltar `clean`.

As colunas de orientação são opcionais. O `RblListSeeder` adiciona instruções gerais
somente quando ainda estão nulas e não sobrescreve nome, estado ou texto customizado.
URLs não são presumidas pelo seeder: o administrador deve cadastrar e revisar os
endereços oficiais no painel.

## Histórico anterior — RBL-5C

## Operação visual dos blocos CGNAT — RBL-5C

O detalhe de um alvo CIDR em `/rbl/targets/{target}` concentra a operação da
varredura incremental. **Progresso do bloco CGNAT** mostra o total de IPs, ciclo,
cursor, verificados, pendentes estimados, listados, limpos, ignorados, erros/timeout,
percentual e horário do último check. A seção **IPs listados neste bloco** contém
somente eventos abertos, com IP (`checked_value`), RBL, datas, resposta, situação da
investigação e acesso ao evento. **Últimos IPs verificados** apresenta os checks mais
recentes com paginação.

`partial` aparece como **Parcial** e significa que o ciclo ainda não terminou; não é
sucesso nem equivale a limpo. **Listado** indica ao menos um IP com evento aberto.
**Limpo** exige ciclo completo sem listagens abertas. **Erro** identifica um ciclo
com erro/timeout e **Ignorado**, consultas skipped. O dashboard exibe esses labels
em português e um resumo compacto; grupos CGNAT exibem totais agregados e progresso
ponderado pelos IPs de cada bloco. Relatórios ordenam blocos por listados, pendentes
e erros, sem gráficos ou consultas DNS adicionais.

O quadro **Próximo lote planejado** é apenas uma estimativa local: mostra até 16 IPs,
listas ativas, checks planejados e o limite por alvo. O botão **Verificar agora —
próximo lote** executa exatamente o próximo lote permitido, nunca o bloco inteiro.
Isso distribui um `/24` (256 endereços, incluindo network e broadcast) por várias
execuções e evita excesso de consultas às RBLs. Com quatro listas IP ativas e teto
de dez consultas, por exemplo, o lote efetivo é de dois IPs e oito checks.

Esta apresentação não integra MikroTik, não lê NAT ou logs CGNAT, não identifica nem
bloqueia cliente, não altera firewall, não faz delist, não executa scripts externos,
não envia alertas de progresso e não altera geração, preview ou download RPZ.

## Blocos IPv4 públicos de CGNAT — RBL-5B

Alvos CIDR IPv4 podem representar os blocos públicos usados por um CGNAT
estático/determinístico. O sistema monitora somente a reputação DNSBL de cada IP
público: ele não consulta configuração do CGNAT e não correlaciona assinante,
porta, sessão ou log NAT.

Por padrão, blocos de até 1.024 endereços (prefixo mínimo `/22`) são aceitos.
CIDRs de até 8 IPs mantêm a verificação compacta existente. Blocos maiores usam
cursor persistente e lotes de até 16 IPs. O lote efetivo pode ser menor para
respeitar simultaneamente os limites de 10 consultas DNSBL e 20 segundos por
alvo. Todos os endereços, inclusive network e broadcast, são considerados.

As opções em `config/rbl.php` são `large_cidr_enabled`, `max_cidr_total_ips`,
`min_cidr_prefix`, `batch_ips_per_run`, `max_checks_per_target` e
`max_seconds_per_target`. Os padrões correspondentes são `true`, `1024`, `22`,
`16`, `10` e `20`. Um `/24` tem 256 IPs, um `/23` tem 512 e um `/22` tem
1.024; `/21` é skipped no padrão. Não aumente o lote sem considerar a quantidade
de RBLs ativas e as condições de uso dos provedores.

`rbl_target_scan_states` registra cursor, ciclo, totais por resultado e um resumo
JSON por IP do ciclo. Cada execução consulta o próximo lote e avança o cursor. Ao
alcançar o fim, o ciclo é concluído e o cursor volta a zero; a execução seguinte
inicia outro ciclo. `rbl_checks.checked_value` e `query` continuam identificando o
IP individual e a consulta reversa. Eventos de dois IPs do mesmo bloco são
independentes por alvo + RBL + IP.

O status `partial` (“Parcial”) significa que o ciclo ainda não cobriu o bloco
inteiro; nunca deve ser interpretado como limpo. `listed` prevalece enquanto
qualquer IP do bloco tiver evento aberto. Somente um ciclo completo, sem listagem,
erro ou skipped, resulta em `clean`. Dashboard, grupo e detalhe do alvo mostram
total, verificados, pendentes, listados, limpos, erros, percentual e ciclo. O
relatório resume blocos e IPs listados; o CSV inclui tipo/valor do alvo, IP
verificado, grupo, ciclo, progresso e status agregado, mantendo neutralização de
fórmulas.

Exemplo: cadastre `45.239.156.0/24` como alvo `cidr`, associe-o a um grupo CGNAT e
use **Verificar agora** ou o scheduler. O botão sempre verifica apenas o próximo
lote. `php artisan rbl:check --target=ID --dry-run` mostra total, cursor, ciclo,
progresso, IPs e checks planejados sem avançar estado ou gravar checks.

Esta fase não integra MikroTik, não lê ou interpreta logs NAT/CGNAT, não identifica
clientes, não executa scripts, não bloqueia cliente, não altera firewall/RPZ, não
faz delist, não consulta ASN e não gera alerta de progresso. IPv6 permanece fora
do escopo e CIDRs acima do limite são skipped. Alertas Telegram continuam somente
nas transições de evento listed/resolved, sem recorrência por lote.

## Investigação operacional — RBL-5A

Em `/rbl/events`, o administrador pode filtrar a classificação de investigação e
abrir **Detalhes**. A rota `/rbl/events/{event}` reúne dados técnicos, último check,
alertas, checks relacionados, reincidência e uma linha do tempo cronológica. A tela
permite registrar uma observação de até 5.000 caracteres e classificar o trabalho
como `pending` (pendente), `investigating` (em investigação), `investigated`
(investigado) ou `false_positive` (falso positivo). Também é possível limpar a
classificação. Cada atualização registra o administrador e um `AuditLog`.

`status` (`open`/`resolved`) é o estado técnico obtido pelos checks DNSBL.
`investigation_status` é somente uma classificação humana independente: um evento
pode estar aberto e investigado. Investigar ou classificar como falso positivo não
resolve o evento, não altera checks históricos e não remove o IP da blacklist.

O botão **Relatório imprimível** abre `/rbl/events/{event}/report`, com dados do
incidente, checks, alertas na timeline, notas e reincidência, além da data e do
administrador que gerou a página. Tokens, destinos Telegram, stack traces e erros
internos não são exibidos. O relatório usa `Cache-Control: private, no-store`.

Reincidência significa quantidade de eventos anteriores para o mesmo alvo + RBL e,
quando existe IP individual, para o mesmo IP + RBL. É um indicador operacional sem
pontuação ou ação automática. `/rbl/reports` apresenta classificações, duração média
aproximada e rankings de eventos reincidentes no período. O CSV ganhou uma seção de
eventos com classificação, IP, observação sanitizada e número de ocorrências anteriores;
a proteção existente contra fórmulas de planilha também cobre esses campos.

Esta fase não implementa delist automático, firewall, bloqueio de clientes,
integração MikroTik, importação ou correlação de logs NAT, scripts externos, ASN,
IPv6 ou alertas repetitivos. Geração, preview e download RPZ não são alterados.

## Alertas operacionais — RBL-4

A migration `2026_09_10_000001_create_rbl_alerts_table.php` adiciona a auditoria
de alertas. Aplicá-la pelo processo normal de implantação antes de usar esta versão.
Esta entrega não aplica migrations ao banco operacional nem envia mensagens reais.

Os alertas começam **desativados**. Em `config/rbl.php`, as opções são:

| Variável | Padrão | Uso |
| --- | --- | --- |
| `RBL_ALERTS_ENABLED` | `false` | Habilita alertas RBL |
| `RBL_ALERT_ON_LISTED` | `true` | Alerta de novo evento |
| `RBL_ALERT_ON_RESOLVED` | `true` | Alerta de resolução |
| `RBL_ALERT_INCLUDE_RESPONSE_CODES` | `true` | Inclui resposta DNSBL |
| `RBL_ALERT_INCLUDE_GROUP` | `true` | Inclui grupo quando existente |

`alert_channels` contém apenas `telegram`; não há canal e-mail nesta fase.
Reutiliza **Configurações → Telegram**: integração ativa, token existente, chat
e tópico opcionais. Também respeita os fallbacks existentes em `services.telegram`.
Nenhum token é copiado para tabelas RBL. Após configurar `RBL_ALERTS_ENABLED=true`
no ambiente, atualizar o cache de configuração pelo processo normal de implantação.
O botão existente de teste Telegram realiza envio real quando acionado pelo operador;
todos os testes automatizados desta entrega usam HTTP fake, sem envio real.

Um novo evento open gera uma tentativa listed; preencher resolved_at por um check
clean gera uma tentativa resolved. Atualizar last_seen_at não reenvia. Error,
timeout, skipped e dry-run não geram alertas. Eventos anteriores não recebem
backfill de listed. Uma nova listagem após resolução cria outro evento e pode alertar.
CIDR mostra o bloco monitorado e o IP individual afetado; mensagens incluem alvo,
lista, datas no fuso da aplicação e duração aproximada em minutos na resolução.
Campos dinâmicos são limitados, sem caracteres de controle, e escapados para HTML.

Os envios ocorrem após commit dos checks/eventos, fora da transação SQLite, pelo
TelegramNotifier existente, com timeout de 5 segundos e sem retries automáticos.
Uma reserva com índice único `(rbl_event_id, type, channel)` impede duplicidade,
inclusive após falhas. Não há lembretes nem cooldown temporal: a deduplicação vale
por toda a vida do evento para cada tipo/canal. Cada IP/lista tem seu próprio evento;
eventos diferentes podem gerar mensagens no mesmo lote, sem agregação nesta fase.
O tempo dos envios soma-se ao orçamento DNS; até dez transições por alvo podem
acrescentar cerca de 50 segundos. Os limites DNS permanecem inalterados.

`rbl_alerts` registra sent, skipped ou failed, hash da mensagem, sent_at e motivo
genérico. Não armazena corpo da mensagem, token ou destino (destination fica null).
sent significa resposta HTTP de sucesso com `ok=true`, sem garantia de leitura.
skipped indica configuração desativada/ausente ou tentativa reservada não concluída.
Interrupção entre reserva e confirmação pode deixar skipped, mesmo após entrega;
interrupção entre commit do evento e callback pode deixar evento sem registro de alerta.
Não há recuperação/reenvio automático nem garantia de entrega exatamente uma vez.
Se o banco de auditoria estiver indisponível, pode ser impossível registrar failed;
a falha é sanitizada e os checks já confirmados são preservados.

Em `/rbl/events`, cada tipo apresenta badge, canal e horário quando enviado; o
motivo genérico de falha/skipped aparece ao passar o cursor no badge. Eventos antigos
sem registro mostram “Sem alerta”. `/rbl/reports` resume enviados, falhos, listed e
resolved enviados pela data de criação do alerta e pelo grupo atual do alvo.
O CSV mantém as colunas anteriores de checks, sem colunas ou resumo de alertas:
checks repetidos não correspondem um a um a transições de evento.
O comando `rbl:check` informa eventos novos/resolvidos e alertas enviados/falhos
da execução; falha Telegram não altera o resultado DNS nem impede completed.

Para investigar failed, verificar configuração administrativa, permissões do bot
no chat/tópico e conectividade. Os logs Telegram guardam apenas status HTTP ou
erro genérico, sem URL, corpo de resposta ou exception contendo credenciais.
Não existe botão de reenvio nesta fase. Corrigir a configuração afeta transições futuras.

Alertas **não fazem delist, não bloqueiam clientes, não alteram firewall e não
integram com CGNAT/MikroTik**. Não há e-mail, scripts externos, ASN, IPv6 ou CIDR
grande nesta entrega. Geração/download/preview e fluxo principal RPZ permanecem
preservados. Investigar a origem do tráfego e qualquer ação operacional continuam
sendo responsabilidades humanas.

### Validação RBL-4 — 10/09/2026

- `php artisan test`: 304 testes passaram, 2.052 assertions, incluindo dez testes novos.
- `composer test`: os mesmos 304 testes passaram; `APP_CONFIG_CACHE` isolado em
  `/tmp/rbl4-composer-config.php`, preservando o cache operacional.
- `php artisan route:list --json`: 117 rotas, 20 RBL com auth/admin.
- `migrate:fresh --seed --force`: passou exclusivamente com `APP_ENV=testing`,
  SQLite `:memory:`, `DB_URL` vazio, cache array e log null.
- `php -l` e `git diff --check`: passaram nos arquivos novos/alterados.
- Cobertura de ciclo listed/resolved, deduplicação, nova ocorrência, CIDR, escapes,
  opções de configuração, erros HTTP/API/transporte, comando resiliente, rollback,
  índice único, UI e filtros de relatórios; testes anteriores de RPZ preservados.
- Sem envio real, migração operacional, inspeção visual em navegador ou commit.

Módulo administrativo independente em `/rbl`, protegido por `auth` e `admin`.
Banco confirmado no `.env` durante a implementação: `DB_CONNECTION=sqlite`.
As novas migrations usam Schema Builder e tipos portáveis para SQLite/MySQL/PostgreSQL.
A validação automatizada usa SQLite em memória; MySQL/PostgreSQL não foram executados.

## Instalação

Aplicar as migrations pelo processo normal de implantação (`php artisan migrate`) e
popular somente as listas com `php artisan db:seed --class=RblListSeeder`.
O DatabaseSeeder também chama esse seeder. Não usar `migrate:fresh` no banco operacional.
O seeder é idempotente e preserva configurações existentes.

Spamhaus ZEN e SpamCop começam ativas. Barracuda começa desativada até confirmação
de acesso/cadastro junto ao provedor. SORBS começa desativada por descontinuação.
É possível cadastrar listas e ativar/desativar listas e alvos no painel.
Cada nova lista cadastrada pela tela começa desativada.

## Consulta e interpretação

IPv4 individual e CIDR IPv4 de até 1.024 endereços são consultados; acima de 8,
a consulta é incremental. `1.2.3.4` em `zen.spamhaus.org` gera
`4.3.2.1.zen.spamhaus.org`. O transporte usa sockets PHP UDP, porta 53, e o primeiro
nameserver IP de `/etc/resolv.conf`. Não usa shell, scripts, subprocessos, fallback
para DNS público, nem modifica serviços do sistema.

Timeout configurável entre 1 e 5 segundos por consulta; orçamento de 20 segundos
para DNS e máximo de 10 consultas por alvo. Listas excedentes recebem skipped.
O limite de 20 segundos refere-se ao DNS; persistência e renderização têm custo adicional.
Há cooldown de um minuto por alvo, throttle de 6 requisições/minuto por administrador
e lock global de 120 segundos via cache Laravel (inclui margem para alertas). Em múltiplas instâncias, utilizar
cache compartilhado com suporte a locks. Não configurar cache array em produção.

- Respostas A em `127.0.0.x`: listed.
- NXDOMAIN e NOERROR sem A/alias: clean.
- Timeout: timeout; SERVFAIL, REFUSED, resposta malformada/truncada ou A inesperado: error.
- Respostas de acesso recusado como `127.255.255.254` não significam blacklist.
- Não há fallback TCP nem consulta TXT nesta fase; `response_text` fica preparado.
- CIDR acima do limite, domínio, hostname, IPv6 e listas incompatíveis geram skipped, sem resolução DNS.
- Sem listas ativas ou alvo desativado: mensagem controlada, sem histórico fictício.

Cada combinação de IP selecionado e lista ativa recebe um check (inclusive as ignoradas). CIDR incompatível ou acima do limite recebe um skipped por lista, com o valor original. O status agregado
de bloco incremental prioriza listed, usa partial durante o ciclo e somente fica
clean após cobertura integral sem erros ou skips.
A data do alvo registra a última execução, mesmo quando todas as consultas são skipped.

Checks, eventos e status do alvo são persistidos em uma transação após o DNS.
Listed abre ou atualiza o evento por alvo/lista/IP em CIDR; clean resolve somente o evento daquele IP. Para alvos individuais permanece o vínculo alvo/lista.
Error, timeout e skipped preservam eventos abertos. Uma nova listagem após resolução
abre outro evento, preservando o histórico. O card de eventos abertos inclui ocorrências
pendentes em alvos/listas desativados; o card de listados considera o último status de
alvos ativos. A tela detalha datas para evitar interpretar resultados antigos como atuais.

## Uso e agendamento

Acesse `/rbl` com uma conta administrativa. Cadastre um alvo em **Novo alvo**,
confirme as listas ativas e use **Verificar agora** para a verificação manual.
O histórico está no link **Ver histórico**. Verificações manuais geram checks e eventos,
mas não `rbl_runs`; o card de última execução refere-se ao comando agendado/Artisan.

Comandos disponíveis, para execução pelo operador após a migration:

```bash
php artisan rbl:check
php artisan rbl:check --target=123
php artisan rbl:check --only-enabled --limit=50
php artisan rbl:check --dry-run
```

Somente alvos enabled sem grupo ou com grupo ativo são processados, inclusive com `--target`. `--only-enabled`
explicita esse padrão e não permite incluir desativados. O limite padrão é 100 alvos,
aceitando de 1 a 1000. Alvos nunca verificados vêm primeiro, seguidos dos mais antigos;
isso distribui os lotes sem repetir sempre os mesmos IDs. Alvos verificados no último
minuto ficam fora da seleção. ID inexistente/desativado ou nenhuma seleção termina
com zero alvos. `--dry-run` mostra alvos elegíveis, tipo, valor, IPs planejados, listas ativas e consultas planejadas (limitadas a 10), sem DNS, locks ou gravação. O tempo limite pode reduzir a quantidade efetiva.

O comando usa o mesmo checker da interface: consultas sequenciais, somente listas ativas,
mesmos limites por alvo e mesma transação de checks/eventos. A expansão de blocos respeita o limite descrito abaixo.
`rbl_checks.rbl_run_id` associa os checks ao lote, sem misturar verificações manuais.
`rbl_runs` registra início, fim, status, duração, alvos processados e totais de checks
por resultado (error_count inclui timeout). Falhas estruturais preservam contagens
parciais e uma mensagem genérica, sem stack trace ou credenciais.

Exit code 0: completed mesmo com listed, skipped, error ou timeout; também simulação
ou execução concorrente ignorada. Exit code 1: argumentos inválidos ou falha estrutural
(banco/cache indisponível, ausência de listas ativas para alvo elegível, falha de
persistência ou impedimento do checker). Argumentos inválidos não criam run.
Se o banco estiver indisponível, pode ser impossível registrar/finalizar o run.
Interrupção abrupta do processo pode deixar status running; não há recuperação
automática desses registros nesta fase.

O Scheduler em `routes/console.php` está configurado a cada **6 horas**, com lote de
100 alvos e `withoutOverlapping`. Há também lock de comando contra chamadas simultâneas,
com expiração de 24 horas, além do lock compartilhado com as verificações manuais.
Use cache persistente com suporte a locks; em múltiplas instâncias, o cache deve ser
compartilhado. Após interrupção abrupta, o lock pode impedir novos lotes até expirar.
Aumentar o lote aumenta o tempo sequencial e o volume DNS; a frequência inicial
recomendada permanece a cada 6 horas. Não há fila/worker nesta fase.

O operador ainda precisa configurar o cron do Laravel Scheduler:

```cron
* * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
```

Use o usuário de execução da aplicação, com acesso ao banco SQLite e cache.
A frequência segue `config('app.timezone')`. Nenhum cron foi instalado e nenhum
comando de monitoramento real foi executado durante esta implementação.
O Scheduler apenas agenda o comando Laravel; não foram adicionados scripts externos.

## Dashboard, eventos e relatórios

- `/rbl`: alvos ativos, último status listed, eventos abertos, resoluções e erros/timeout
  nas últimas 24 horas, última execução e até 10 itens em cada resumo operacional.
- `/rbl/events`: paginação de 25 eventos; status atual open/resolved/all, alvo, RBL e
  datas. O período inclui eventos ativos em qualquer momento do intervalo, inclusive
  eventos antigos ainda abertos. A duração é em minutos inteiros entre primeira
  detecção e resolução (ou agora), nunca negativa. A última resposta é a última
  resposta listed, preservando a semântica da RBL-1.
- `/rbl/reports`: datas inicial/final inclusivas, padrão últimos 30 dias no fuso da
  aplicação. Checks usam checked_at; eventos abertos usam first_seen_at, mesmo que
  depois resolvidos; resoluções usam resolved_at. Rankings mostram até 20 alvos/valores
  e 20 listas por quantidade de checks listed, não por eventos distintos.
- **Exportar CSV** usa as datas do formulário e inclui resumo e todos os checks do
  período em lotes de 500 via streaming. Escapa CSV, remove caracteres de controle
  e prefixa possíveis fórmulas de planilha. Não usa shell. A exportação mantém
  auth/admin e nome `rbl-report-YYYY-MM-DD-to-YYYY-MM-DD.csv`.

## Limites desta fase

Sem delist automático, firewall, bloqueio de clientes, integração MikroTik/CGNAT,
ações CLI operacionais sobre infraestrutura, Unbound, agente remoto, reload ou apply.
O único novo comando é o monitor Laravel `rbl:check`. Não há scripts externos,
consulta agressiva de blocos, fila assíncrona ou recuperação automática de runs.
CIDR acima de 1.024 IPs, domínio, hostname e IPv6 continuam skipped. Grupos CGNAT organizam reputação de IPs públicos; não correlacionam clientes ou traduções NAT.
A geração/download/preview RPZ e o RpzZoneBuilder não participam do módulo.

Resultados clean significam ausência de listagem na resposta recebida; não garantem
entrega de e-mail nem reputação universal. Respeitar as condições de uso dos provedores.
Spamhaus documenta códigos de erro de acesso em:
https://docs.spamhaus.com/datasets/docs/source/70-access-methods/data-query-service/040-dqs-queries.html

## Próximas fases

- Fase posterior: avaliar fila assíncrona e limites por provedor, mediante escopo próprio.
- RBL-4: alertas Telegram implementados; e-mail fora desta entrega.
- RBL-5: correlação com logs CGNAT para investigação de clientes suspeitos.
- RBL-6: ações CLI auditadas, com allowlist e confirmação humana.

## Validação anterior — RBL-1

- `php artisan test`: 270 testes passaram, incluindo 19 testes novos do módulo.
- `composer test`: suíte completa passou (neste ambiente root, com `COMPOSER_ALLOW_SUPERUSER=1`).
- `php artisan route:list`: 106 rotas, incluindo 9 rotas RBL protegidas.
- `APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= CACHE_STORE=array LOG_CHANNEL=null php artisan migrate:fresh --seed --force`: passou exclusivamente em memória.
- `php -l`: arquivos PHP do módulo e arquivos compartilhados alterados sem erros.
- `git diff --check`: sem erros de whitespace.
- Consulta DNS real ao endereço de teste `2.0.0.127.bl.spamcop.net`: `127.0.0.2`, listed, 0,256s em 09/09/2026. Não consultou alvos de clientes.

A migration e o seed não foram aplicados ao banco operacional durante a implementação.
A consulta real valida o transporte disponível neste ambiente; os testes de falhas e
transições usam respostas determinísticas e não dependem dos provedores externos.

## Validação RBL-2

Os testes usam SQLite em memória, cache array e DNS simulado, incluindo seleção e
limites do comando, execução parcial com falha, locks, ciclo de eventos, duração,
períodos inclusivos, rankings, CSV e autorização. A migration operacional permanece
pendente do processo de implantação; não usar migrate:fresh em produção.

Resultados em 09/09/2026:

- `php artisan test`: 284 testes passaram, 1.903 assertions (14 testes novos RBL-2).
- `COMPOSER_ALLOW_SUPERUSER=1 composer test`: mesmos 284 testes passaram.
- `php artisan route:list`: 108 rotas, incluindo 11 rotas RBL.
- `APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= CACHE_STORE=array LOG_CHANNEL=null php artisan migrate:fresh --seed --force`: passou em memória.
- `php -l`: 47 arquivos PHP novos/alterados no workspace sem erros.
- `git diff --check`: passou.

## Pós-implantação — 09/09/2026

Após a implementação, a migration RBL-2 foi aplicada ao SQLite operacional e o
RblListSeeder foi executado. Não há migrations pendentes. O cron foi instalado em
`/etc/cron.d/dns-panel-rpz-scheduler`, com usuário www-data e caminho
`/opt/dns-panel-rpz`, chamando o Scheduler a cada minuto (RBL a cada 6 horas).
Isso substitui as pendências de implantação descritas no registro de implementação.

Validação por requisições internas autenticadas: `/rbl` (cards), `/rbl/events`,
`/rbl/reports`, CSV e preview/download RPZ da lista 7 retornaram HTTP 200.
Não foi feita inspeção visual no navegador. Os 63 testes focados em RBL/RPZ passaram.
Uma consulta real ao IPv4 autorizado pelo operador criou o run 2 completed,
com 1 alvo, 2 checks (1 listed, 1 clean), zero erros e evento aberto correspondente.
Nenhum delist ou alteração de firewall foi executado.


## Grupos e CIDR — RBL-3

Em **Grupos RBL → Novo grupo**, crie “CGNAT Bloco 1”, selecione `cgnat` e mantenha
Ativo. O slug pode ser informado ou será gerado pelo nome; deve ser único.
Em **Novo alvo** ou **Editar alvo**, selecione o grupo. Alvos antigos sem grupo
continuam válidos; `category` permanece disponível. A edição altera nome, grupo,
categoria, descrição e estado; tipo/valor são fixos para preservar o histórico.
Desativar um grupo suspende checks manuais e agendados, sem resolver eventos.

`/rbl/groups` permite listar, criar, detalhar, editar e ativar/desativar grupos.
O detalhe mostra total de alvos, listados, eventos abertos, último check e ID da
execução quando houver, alvos paginados e dez eventos recentes. Todas as rotas
exigem auth/admin. O dashboard mostra contagens por grupo e filtro da tabela de
alvos (os demais cards continuam globais). Eventos e relatórios aceitam grupo.
O relatório e CSV incluem resumo por grupo e grupo de cada check. A associação
considerada nos relatórios é a atual; mover um alvo reagrupa seu histórico.
“Eventos abertos” no relatório conta primeiras detecções no período, inclusive
posteriormente resolvidas; no dashboard/detalhe conta eventos atualmente open.

### Limite e exemplos

Historicamente, `RBL_MAX_CIDR_IPS` definiu o teto compacto de 8 endereços. Na
RBL-5B ele separa CIDR compacto de incremental; o teto global é
`RBL_MAX_CIDR_TOTAL_IPS` (1.024 no padrão).
A expansão inclui endereços de rede e broadcast; não limita a IPs úteis.

- `203.0.113.0/30`: .0, .1, .2 e .3 (4 IPs).
- `192.0.2.0/29`: .0 até .7 (8 IPs).
- `192.0.2.0/24`: 256 IPs, consultados incrementalmente.
- `2001:db8::/126`: skipped, IPv6 ainda não suportado.
- CIDR com bits de host, como `203.0.113.2/30`, expande a rede .0/30;
  o valor cadastrado permanece intacto.

O teto anterior de **10 consultas / 20 segundos de DNS por alvo**, timeout de
1–5 segundos por RBL, cooldown de um minuto e locks foram preservados.
Assim, /30 com duas RBLs planeja 8 consultas; /29 com duas RBLs permite no máximo
10 consultas e registra as seis restantes como skipped. A ordem é por lista e IP;
blocos maiores reduzem o lote efetivo quando necessário. Não interpretar uma
verificação parcial como bloco limpo. `--limit=N` limita alvos, não IPs internos.
`rbl:check --target=ID --dry-run` informa o planejamento sem criar checks ou runs.

### Status e eventos por endereço

`rbl_targets.value` mantém o CIDR; `rbl_checks.checked_value` e `query` registram
IP individual e nome DNSBL. Qualquer listed torna o alvo listed. Sem listed,
erro/timeout torna o alvo error (inclusive em execução parcialmente útil, para
não esconder falhas); skipped torna o CIDR skipped; todos clean tornam clean.

Cada IP listado abre evento por target CIDR + RBL + `last_checked_value`.
Por exemplo, 203.0.113.2 listado no bloco 203.0.113.0/30 aparece com nome do alvo,
grupo, bloco monitorado, IP listado, RBL e resposta 127.0.0.4. Vários IPs listados
produzem eventos distintos. Clean de .1 não resolve evento de .2; clean de .2
resolve o evento correspondente. Error/timeout/skipped mantêm eventos abertos.

CIDR acima do limite, IPv6, ASN, integração real CGNAT/logs NAT, MikroTik, firewall,
bloqueio de clientes, delist automático, scripts externos e ações CLI sobre
infraestrutura continuam fora do escopo. Apenas o comando Laravel já existente
foi atualizado. Geração, preview, download e bloqueio RPZ foram preservados.

A migration RBL-3 deve ser aplicada pelo processo de implantação. As validações
usam SQLite de teste e DNS simulado; nenhuma consulta operacional é necessária.

### Validação RBL-3 — 09/09/2026

- `php artisan test`: 294 testes passaram, 2.006 assertions.
- `composer test`: mesmos 294 testes passaram; `APP_CONFIG_CACHE` apontado para
  `/tmp/rbl3-config.php` para isolar o config:clear do cache operacional.
- `php artisan route:list --json`: 117 rotas, 20 RBL, todas com auth/admin.
- `migrate:fresh --seed --force`: passou com `APP_ENV=testing`, SQLite `:memory:`,
  `DB_URL` vazio, cache array e log null; nenhum banco operacional foi recriado.
- `php -l`: 28 arquivos PHP/Blade novos ou alterados sem erros de sintaxe.
- `git diff --check`: passou; status revisado, alterações ainda sem commit.
- Dez testes novos cobrem grupos, autorização, associação, filtros, CSV, CIDR,
  eventos independentes por IP, execução limitada e dry-run sem DNS/gravação.
  A expectativa antiga de CIDR grande foi atualizada de unchecked para skipped.
- Scheduler e fluxo RPZ sem alterações; testes existentes preservados.
- Sem implantação da migration RBL-3, consulta DNS real ou inspeção visual em navegador.
