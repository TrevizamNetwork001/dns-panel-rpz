# Runbook de incidentes — DNS Panel RPZ

Guia rápido pra quando algo dá errado em produção (`rpz.trevizamnetwork.com.br`). Comandos assumem SSH no servidor `paineldns`, dentro de `/opt/dns-panel-rpz`.

> **Desde a migração para Docker (set/2026):** a aplicação roda inteira em containers (`docker compose`, ver [`compose.yml`](compose.yml)). Não há mais PHP nem php-fpm instalado no host — todo `artisan` roda via `docker compose exec app php artisan ...`. Só ficam no host: nginx de borda (TLS), Certbot, fail2ban e os timers systemd de healthcheck/renovação de certificado. Detalhes completos em [`deploy/README.md`](deploy/README.md).

## Primeiro passo, sempre

```bash
curl -sI https://rpz.trevizamnetwork.com.br/up   # health route do Laravel
docker compose logs --tail 100 app               # logs da aplicação (LOG_CHANNEL=stderr, não tem mais storage/logs/laravel.log)
docker compose exec app php artisan health:check # roda a checagem na hora, mostra o motivo
```

Olhe também `/seguranca` e `/auditoria` no painel — a maioria dos incidentes já deixa rastro lá antes de você precisar entrar por SSH.

## Revisão da lista manual de segurança

A lista global **Segurança — Malware / C2 / IoT** contém bloqueios manuais separados da lista **ANATEL — Bloqueios Oficiais**. Antes de adicionar um domínio, registre a fonte da evidência e confirme se o bloqueio deve atingir o domínio e seus subdomínios. Para WPAD, mantenha apenas `wpad.domain.name`: não adicione `wpad`, `domain.name` ou um curinga genérico.

Os domínios `jhkkjkj.com`, `weerrhoop.cc` e `ojsbdjn.com` foram retirados da RPZ e têm exclusões exatas na ANATEL. Não os reative sem evidência concreta e revisão registrada. Após qualquer ajuste de dados, confira a auditoria, a consulta RPZ gerada e aguarde o ciclo normal de aproximadamente uma hora nos clientes.

Detalhes da alteração de 20/09/2026: [`docs/SEGURANCA-2026-09-20.md`](docs/SEGURANCA-2026-09-20.md).

---

## Site fora do ar (HTTP não responde / 502 / 500)

1. `docker compose ps` — algum container caiu, reiniciando em loop, ou "unhealthy"?
2. `systemctl status nginx` — o nginx de borda (host) caiu? (o container `nginx` é interno, só escuta em `127.0.0.1:8082`)
3. `docker compose exec app php artisan health:check` — roda a checagem manualmente, mostra disco/certificado/site.
4. `docker compose logs --tail 200 app` — erro de aplicação (query, permissão, config).
5. Se for 502/504: `docker compose logs --tail 100 nginx` — container nginx sem conseguir falar com o `app` (php-fpm travado ou container `app` reiniciando).
6. Restart seguro:
   ```bash
   docker compose restart app       # derruba e sobe de novo só o container da aplicação
   systemctl reload nginx           # reload do nginx de borda, nao restart -- evita drop de conexoes em andamento
   ```
7. Se nada disso resolver, confirme que não foi um deploy quebrado: `git log --oneline -5` e considere reverter (ver seção "Reverter um deploy").

## Servidores de clientes não sincronizam RPZ (zonefile não atualiza)

1. Confira o servidor específico em `/servidores/{id}` no painel — "Última sincronização" e o histórico de sync.
2. Teste a URL manualmente:
   ```bash
   curl -s "https://rpz.trevizamnetwork.com.br/rpz/{token}.zone" | head -20
   ```
   - 404 → servidor ou empresa inativa, ou ACL de IP bloqueando (veja `/servidores/{id}` → Restrição de IP).
   - 403 no endereço curto por empresa (`/rpz/{slug}.zone`) → o IP de quem consultou não está liberado em nenhum servidor da empresa. O IP que o painel enxergou aparece no log do nginx (`docker compose logs nginx | grep /rpz/`, último campo) e o pedido em `/auditoria` como "Acesso negado ao endpoint RPZ". Se o cliente informou o IP no cadastro, ele fica **pendente**: abra o servidor → **Restrição de IP → Gerenciar IPs → Liberar**. O Unbound tenta de novo sozinho (com espera crescente); se quiser acelerar, use `unbound-control auth_zone_transfer rpz.trevizamnetwork.com.br` no DNS do cliente (com controle local habilitado e o nome definido no bloco `rpz:`).
   - Zonefile vazio de domínios → nenhuma lista ativa vinculada, ou listas sem domínios ativos.
   - Para solicitar nova busca antes do intervalo: abra o servidor no painel → **Sincronizar agora** → **Solução de problemas** → **Copiar** e execute no DNS do cliente como root ou com sudo. O painel não executa comandos remotamente. `ok` confirma a solicitação, não a conclusão: confira o histórico do painel e os logs do Unbound. Reiniciar pode reutilizar a zona salva. Se o nome da zona foi personalizado, use o nome local; `unbound-control list_auth_zones` lista as zonas configuradas.
3. Valide a sintaxe do zonefile gerado (precisa de `bind9-utils` instalado):
   ```bash
   curl -s "https://.../rpz/{token}.zone" > /tmp/check.zone
   named-checkzone rpz.trevizamnetwork.com.br /tmp/check.zone
   ```
4. Do lado do cliente: confirmar que o módulo `respip` está habilitado no bloco `server:` (`module-config: "respip validator iterator"` — `respip` primeiro; sem ele o RPZ não bloqueia nada e o Unbound não avisa) e que o bloco `rpz:` no `unbound.conf` dele está **depois** do bloco `server:` (antes, se usar `hyperlocal`), e rodar `unbound-checkconf` antes de qualquer restart.

### Solicitar nova busca sem esperar o refresh

Execute no **servidor DNS do cliente**, como root ou com sudo, com `unbound-control` habilitado:

```bash
unbound-control list_auth_zones
unbound-control auth_zone_transfer rpz.trevizamnetwork.com.br
```

O segundo argumento deve corresponder ao `name:` do bloco `rpz:`. O painel disponibiliza esses comandos na seção recolhível **Configuração técnica → Solução de problemas**; ambos têm botão **Copiar**. Não há execução remota ao clicar.

- Erro de conexão/controle desabilitado: conferir a configuração local do controle do Unbound.
- Zona não encontrada: conferir os nomes retornados por `list_auth_zones` e a configuração efetivamente carregada.
- Retorno `ok`: aguardar a transferência, conferir nova consulta/contagem no painel e carregamento nos logs do Unbound. O registro do painel é criado antes do término da resposta.
- Reinício sem atualização: o serviço pode reutilizar a zona salva. `auth_zone_reload` também relê o arquivo local; use `auth_zone_transfer` para solicitar busca.
- Zona sem domínios: conferir listas ativas, vínculos e seus horários. Se a consulta ocorreu antes dos vínculos, uma resposta HTTP 200 pequena pode conter somente cabeçalho/canário.

Caso diagnosticado e mudanças de interface: [registro de 20/09/2026](docs/RELATORIO-2026-09-20.md).

### Unbound não atualiza RPZ por HTTPS (regressão de versão, não de SO)

Há uma regressão conhecida no Unbound que pode impedir transferências de RPZ configuradas com `url: "https://..."`. O painel pode registrar a consulta, e `unbound-control auth_zone_transfer ...` pode retornar `ok`, mas o zonefile continua antigo ou contém apenas o cabeçalho. No log pode aparecer `http took too long, dropped` ou timeout da transferência.

**Atualização (24/09/2026) — a causa não é "nunca atualiza sozinho".** Investigação ao vivo em produção (cliente Uniao Networks) mostrou, pela auditoria do próprio painel (`server_sync_logs`), que um servidor **ainda no Unbound 1.13.1** vinha sincronizando sozinho, de hora em hora, o dia inteiro, sem nenhum cron configurado (`crontab -l` vazio) — batendo certinho com o `refresh: 3600` do SOA. Ou seja: o refresh automático periódico **funciona normalmente** em versões antigas, desde que a rede esteja saudável. A causa real é mais específica: o cliente HTTP interno do Unbound (nas versões afetadas) **não tem timeout nem fallback IPv4/IPv6** na busca da zona. Se uma tentativa de refresh (automática ou forçada) coincide com uma falha de rede — o caso mais visto é **rota IPv6 quebrada até o painel** — a busca fica pendurada indefinidamente, sem erro nenhum no log, e como não solta, **bloqueia também os ciclos de refresh seguintes**, fazendo a zona parecer "travada pra sempre" a partir daquele ponto. Um `systemctl restart unbound` costuma destravar na hora (reseta o estado preso), mesmo sem mexer em nenhuma configuração — foi o que resolveu o caso do cliente SBCNET LTDA nesse mesmo dia. Diagnóstico rápido pra confirmar rota IPv6 quebrada como gatilho:

```bash
curl -6 -sS -o /dev/null -w 'HTTP=%{http_code}\n' --connect-timeout 8 https://rpz.trevizamnetwork.com.br/   # se travar/HTTP=000...
curl -4 -sS -o /dev/null -w 'HTTP=%{http_code}\n' --connect-timeout 8 https://rpz.trevizamnetwork.com.br/   # ...mas isso funciona, é IPv6 quebrado no caminho
unbound-control get_option do-ip6
```

Isso não muda a solução definitiva (atualizar pra ≥1.26.1, que já tem timeout/fallback correto) nem o contorno via cron abaixo — só corrige o entendimento da causa, importante pra não perder tempo tentando corrigir "o refresh automático" quando na real o problema é uma trava pontual sem timeout.

**Contorno confirmado (28/09/2026) — servir a zona por HTTP em vez de HTTPS.** No cliente SPEED FIBER CONNECTION (servidores DNS-01 e DNS-02, Unbound 1.13.1), `auth_zone_transfer` retornava "ok" mas não fazia **nenhuma tentativa** de resolver/conectar (log filtrado por `journalctl -u unbound | grep trevizamnetwork` ficava completamente vazio após o comando). Um `systemctl restart unbound` sozinho **não resolveu** (testado e confirmado, continuava sem `zonefile` em disco). Só depois de trocar a `url:` do bloco `rpz:` de `https://` para `http://` (mesmo host/caminho, via uma exceção pontual no nginx do host pra não redirecionar essa URL específica pra HTTPS) e reiniciar, a zona carregou. Como o restart sozinho já tinha sido descartado antes nesse mesmo cliente, a comparação ficou isolada — é evidência de que o problema está ligado à camada HTTPS/TLS do cliente HTTP interno dessas versões antigas.

**Confirmado com o tempo**: sem nenhuma intervenção manual depois da troca, os dois servidores se auto-atualizaram sozinhos no ciclo seguinte, batendo certinho com o `refresh: 3600` do SOA (~59min depois do último evento, em ambos) — não foi só a transferência forçada da hora, o refresh automático periódico continua funcionando normalmente em HTTP.

**Isso é uma alternativa real ao cron** pra quem não pode atualizar o Unbound na hora: servir (ou apontar o cliente pra) a zona via HTTP em vez de HTTPS. Ressalva óbvia: a zona passa a trafegar sem criptografia nesse meio tempo — quem decidir usar isso como contorno deve estar ciente da troca de segurança por disponibilidade, e tratar como paliativo até atualizar o Unbound de verdade, igual ao cron.

```nginx
# Exceção pontual no gateway nginx (bloco :80, antes do redirect catch-all),
# só pra uma URL específica de RPZ continuar em HTTP puro:
location = /rpz/<slug-da-empresa>.zone {
    proxy_pass http://127.0.0.1:8082;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header Connection "";
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $remote_addr;
    proxy_set_header X-Forwarded-Host $host;
    proxy_set_header X-Forwarded-Proto http;
    proxy_connect_timeout 10s;
    proxy_read_timeout 120s;
    proxy_send_timeout 120s;
    proxy_redirect off;
}
```
E no `unbound.conf` do cliente, trocar `url: "https://..."` por `url: "http://..."` no mesmo caminho.

**Efeito colateral grave em versões antigas: a sincronização derruba o DNS por alguns segundos/minutos.** Descoberto em produção (29/09/2026, cliente SPEED FIBER CONNECTION): toda vez que a zona RPZ (~19-20MB, centenas de milhares de domínios) é baixada e aplicada, o Unbound 1.13.1 fica momentaneamente sem responder consultas normais — o monitoramento do próprio cliente (Zabbix) reportava "DNS down" com o incidente batendo, quase ao segundo, com o horário exato de cada sincronização (`server_sync_logs`):

```
DNS-01 — Zabbix 22:02:42  vs  painel 22:02:31  (11s depois)
DNS-01 — Zabbix 00:03:14  vs  painel 00:03:03  (11s depois)
DNS-02 — Zabbix 03:55:43  vs  painel 03:55:31  (12s depois)
```

Como a sincronização é de hora em hora, isso significa uma mini-queda de DNS **recorrente, todo santo ciclo**, não um evento raro — mesmo que o cliente não reporte (pode não perceber a causa, ou o serviço deles absorver o blip). Já tinha sido observado antes, de forma anedótica, no cliente Fast Net Telecomunicações: o mesmo sintoma acontecia lá com Unbound antigo + RPZ ativo; desativar o RPZ fazia o alarme parar. Depois de atualizar o Unbound da Fast Net pra 1.26.x, o Zabbix parou de alarmar — **mas isso não quer dizer que a trava sumiu de vez**.

**Correção importante (29/09/2026):** um teste ativo (disparar `dig` em loop de 1s contra o próprio resolver enquanto força a sincronização) provou que, mesmo na Fast Net já atualizada pra 1.26.x, **a trava ainda acontece** — 3 consultas seguidas deram timeout (~9s sem resposta), começando uns 20s depois de forçar a transferência. "Zero alarme no Zabbix" não é "zero impacto": é só que a checagem do monitoramento (a cada 1-5 min, tipicamente) não é frequente o bastante pra pegar uma falha de ~9 segundos. A atualização de versão **encurta bastante** a trava (de possivelmente minutos, nas versões antigas, pra segundos), mas não elimina o comportamento bloqueante em si — que é uma limitação arquitetural conhecida do próprio Unbound, ver [issue #998](https://github.com/NLnetLabs/unbound/issues/998) (ainda aberto) e [issue #636](https://github.com/NLnetLabs/unbound/issues/636).

**Tentativa de mitigação no painel, testada e descartada (29/09/2026):** trocamos o serial da zona RPZ de `now()->timestamp` (sempre diferente, a cada busca) pra um hash do conteúdo real da lista de domínios (`RpzZoneBuilder::computeSerial()`), na hipótese de que o Unbound comparasse o serial **antes** de gastar processamento e pulasse a reaplicação quando nada mudou. **Testado ao vivo na Fast Net (1.26.1) e refutado**: rodamos o teste ativo de `dig` em loop duas vezes seguidas, sem nenhuma mudança na lista entre uma e outra (confirmado: nenhum domínio alterado desde 07:08, testes às 08:52 e 08:54) — travou as duas vezes, do mesmo jeito, mesmo com o serial idêntico entre as duas buscas.

Conclusão: o mecanismo de busca via `url:` aparentemente faz o **parsing do arquivo inteiro antes** de qualquer comparação de serial — o gasto pesado (que trava as consultas) é processar o texto em si, não a etapa de aplicar/trocar a zona internamente. Corrigir o serial não evita esse custo. A mudança do serial em si não tem efeito colateral negativo (continua correta, só não resolve o travamento) — mantida no código, mas **não conte com ela pra reduzir a frequência da trava**.

Alavancas que realmente reduzem a frequência (com trade-off, nenhuma solução perfeita disponível hoje):
- **Aumentar o intervalo de refresh** (de 1h pra 6h/12h) — reduz proporcionalmente quantas travas por dia, ao custo de atraso na propagação de bloqueios novos. Ainda não implementado; exigiria decidir o intervalo aceitável.
- **Reduzir o tamanho da zona** (menos domínios agregados) — ataca a causa de raiz (é o volume de texto que trava o parsing), mas reduz cobertura de bloqueio.
- **Atualizar o Unbound pra 1.26.x** — não elimina, mas reduz a duração da trava (de possivelmente minutos, em versões antigas, pra segundos).
- **Aguardar correção upstream** — é limitação arquitetural conhecida do próprio Unbound (issue #998, ainda aberto).

**Ideias levantadas em discussão (29/09/2026), ainda não testadas nem implementadas:**
- **Dividir a zona agregada em múltiplos blocos `rpz:` menores** (por categoria: ANATEL, ameaças, phishing, etc.), cada um com sua própria URL e refresh escalonado em horários diferentes. Hipótese: um arquivo menor deve parsear mais rápido, e escalonar os horários transforma uma trava longa concentrada em várias travas curtas espalhadas. Ressalva: exige o cliente reconfigurar o `unbound.conf` com múltiplos blocos (mudança operacional em cada servidor, não só no painel); não está confirmado se o Unbound serializa o carregamento de zonas globalmente (o que anularia parte do ganho se os refreshes coincidirem no tempo).
- **Testar se o Unbound manda cabeçalho HTTP condicional** (`If-None-Match`/`If-Modified-Since`) na busca da zona via `url:`. Se mandar, o servidor poderia responder `304 Not Modified` quando o conteúdo não mudou, evitando o download **e** o parsing por completo (diferente do teste do serial, que só mudava o conteúdo do arquivo, não evitava a transferência em si). Se não mandar, essa via é inviável do nosso lado.
- **Métrica de tempo de aplicação/timeouts por sincronização** — valiosa, mas o painel não observa isso remotamente (o travamento acontece local no Unbound do cliente); exigiria um agente rodando no lado do cliente reportando de volta, escopo maior que um ajuste pontual.

Ordem sugerida pra quando for testar: 1) confirmar headers condicionais (rápido); 2) testar divisão em 2-4 zonas com refresh escalonado, medindo o pico de trava; 3) só então decidir entre aumentar refresh vs reduzir lista.

Prioridade alta continua sendo: cliente rodando versão antiga com RPZ realmente sincronizando (seja via HTTPS normal ou pelo contorno HTTP acima) está sofrendo essa trava a cada vez que a lista muda, mesmo sem saber.

**Não é específico de uma versão do Debian.** Já foi visto em Unbound **1.13.x, 1.17.x e 1.22.x**, em clientes rodando Debian **10, 11, 12 e 13** — ou seja, o problema segue o **pacote do Unbound**, não a versão do sistema operacional; um Debian 13 recém-instalado pode ter vindo com um Unbound ainda afetado. Em **1.26.1** o problema não ocorre (confirmado em produção). O relato original está num tópico/issue do próprio repositório do Unbound (NLnet Labs) descrevendo esse mesmo sintoma com `rpz: url: https://...`. Antes de descartar a causa, confira a versão instalada, não só o SO:

```bash
unbound-control status | head -1    # ex.: version: 1.22.0  <- ainda pode estar afetado
```

**O painel já detecta isso sozinho, sem precisar entrar em cada servidor.** O Unbound se identifica no `User-Agent` de toda requisição que ele faz pra buscar a zona (é assim que aparece `"unbound/1.22.0"` no access log do nginx); o painel guarda esse valor a cada sincronização (`servidores.last_user_agent`, `server_sync_logs.user_agent`) e mostra a versão na tela do servidor (`Servidor::unboundVersion()`). Quando a versão é uma das afetadas (`Servidor::unboundVersionAfetada()`, `< 1.26.1`):
- selo **"Unbound desatualizado"** na listagem de endpoints (admin) e **"Precisa atualizar"** na listagem do cliente;
- aviso destacado no topo da tela do servidor, técnico pro admin e em linguagem simples pro cliente.

Isso é passivo — não precisa de SSH nem de nada configurado no cliente, só depende de pelo menos uma sincronização já ter acontecido depois do deploy dessa feature (24/09/2026 em diante). Servidor que nunca sincronizou, ou sincronizou só antes disso, aparece sem essa informação até a próxima busca.

O diagnóstico deve separar a rede do cliente Unbound:

```bash
curl -L --connect-timeout 10 --max-time 90 \
  -o /tmp/rpz-teste.zone \
  -w '\nHTTP=%{http_code}\nTempo=%{time_total}s\nBytes=%{size_download}\n' \
  'https://rpz.trevizamnetwork.com.br/rpz/<slug-ou-token>.zone'
wc -l -c /tmp/rpz-teste.zone
unbound-control list_auth_zones
wc -l -c /var/lib/unbound/<zona>.zone
```

Se o `curl` baixar uma zona grande com HTTP 200, mas o arquivo do Unbound não mudar, a conectividade e o endpoint estão funcionando; o problema está na versão do Unbound do cliente.

**O que quebra exatamente:** o download inicial funciona normalmente (`unbound-control auth_zone_transfer <nome-da-zona>` baixa a zona pela primeira vez, do zero, como se o Unbound estivesse subindo agora), e o refresh automático periódico (via `refresh` do SOA) também funciona normalmente enquanto a rede do cliente até o painel estiver saudável — ver nota de 24/09/2026 acima. O que quebra é uma tentativa específica (automática ou forçada) travar por falta de timeout/fallback numa falha de rede pontual (ex.: IPv6 quebrado); sem esse timeout, a busca nunca solta, e como não solta, os ciclos seguintes de refresh também não acontecem. Sem intervenção (restart, ou forçar de novo), o zonefile fica congelado na última versão baixada com sucesso antes da trava.

**Solução definitiva:** atualizar o **pacote Unbound** (não só o SO) para uma versão onde o bug não reproduz — **1.26.1 confirmado sem o problema**, com a atualização automática pelo serial funcionando normal (a cada `refresh`, sem precisar de script nem cron). Em Debian 13, `apt update && apt upgrade unbound` costuma resolver, já que o repositório recebe atualizações do pacote depois do lançamento inicial da distro; em Debians mais antigos pode não haver backport disponível, exigindo migrar o SO.

```bash
unbound-control status | head -1            # confirma a versão nova
unbound-control auth_zone_transfer <nome-da-zona>
```

**Contorno temporário, se não puder atualizar o Unbound na hora:** força a busca manualmente, ou agenda isso via cron/script no próprio cliente, até a atualização definitiva:

```bash
# cron do cliente Unbound, ex.: a cada hora
0 * * * * unbound-control auth_zone_transfer <nome-da-zona>
```

Isso não corrige a causa, só disfarça — o cliente sempre depende desse script rodando; se ele parar (cron falhou, script apagado), o zonefile volta a ficar desatualizado sem nenhum aviso.

Confirme a troca do serial e o crescimento do zonefile antes de considerar a sincronização resolvida. Qualquer cliente com Unbound abaixo de 1.26.1 é candidato a esse problema, independente da versão do Debian — verifique sempre `unbound-control status`, não só a versão do SO.

Referência: [NLnet Labs Unbound issue #429 — `rpz: url:` com HTTPS quebrado como regressão em 1.13.1](https://github.com/NLnetLabs/unbound/issues/429).

## Servidor sumiu do painel (removido por inatividade)

A limpeza automática (`servidores:limpar-inativos`, desligada por padrão) remove servidor sem consultar a zona além do prazo configurado. Confirme em `/auditoria` → categoria **Servidores** (`servidor.inatividade_avisada` e `servidor.removido_inatividade` mostram nome, empresa e há quantos dias estava parado). Não há como desfazer pela tela: recadastre o servidor (o token muda, então o `url:` do `unbound.conf` do cliente precisa ser atualizado) ou restaure do backup do banco. Pra impedir que aconteça de novo, desligue a função em `/configuracoes` → **Geral**, ou aumente o prazo. Simulação segura: `docker compose exec app php artisan servidores:limpar-inativos --dry-run`.

## Lista externa não atualiza

1. `docker compose ps external-sync` — container ativo? (roda em loop próprio, sincroniza a cada `AUTOMATION_INTERVAL` segundos, padrão 6h — ver `compose.yml`)
2. `docker compose exec app php artisan external:sync` — roda todas as fontes externas na mão e mostra o resultado individual de cada uma.
3. Motivo comum de abort (por design, não é bug): um feed retornou menos de 100 domínios — a proteção evita esvaziar a lista quando a fonte está fora do ar ou muda de formato. Veja `docker compose logs external-sync`.
4. Lista pode estar pausada manualmente: confira `sync_ativo` em `/listas` (badge "Pausar sync" vira "Reativar sync" quando pausada).

## Importação ANATEL falha com status "Falhou" (PDF grande)

Ofícios com muitas páginas (170+) podem levar 1-2min pra extrair — se passar do timeout configurado, a importação vira `failed` sem domínio nenhum (`pages: 0`, `candidates_count: 0`).

1. Confira quanto tempo o job realmente rodou: `docker compose logs queue --since 1h | grep ProcessAnatelImport` — se o tempo bateu certinho com `ANATEL_EXTRACT_TIMEOUT` (`/etc/dns-panel-rpz/app.env`, padrão 300s), foi timeout, não PDF corrompido.
2. Teste rodando a extração direto, sem limite, pra confirmar que o arquivo é válido e ver quanto tempo leva de verdade:
   ```bash
   docker compose exec app time /opt/anatel-venv/bin/python /var/www/html/scripts/anatel_pdf_extract.py <caminho-do-pdf-em-storage/app/private/anatel/...>
   ```
3. Se precisar de mais tempo, aumente `ANATEL_EXTRACT_TIMEOUT` no `.env` **e** o `--timeout` do `queue:work` no `compose.yml` (sempre com folga acima do primeiro, senão a fila mata o job antes do timeout interno disparar) — depois `docker compose up -d --force-recreate app queue`.
4. Reenfileirar a mesma importação sem precisar reenviar o arquivo:
   ```bash
   docker compose exec app php artisan tinker --execute="
   App\Models\AnatelImport::where('id', <ID>)->update(['status'=>'pending','progress'=>0,'error'=>null]);
   App\Jobs\ProcessAnatelImport::dispatch(<ID>);
   "
   ```

## Banco de dados corrompido ou dado errado

**Nunca edite o SQLite de produção direto sem backup antes.** O banco (`/data/database.sqlite` dentro dos containers) e os backups (`/backups`) vivem em volumes Docker nomeados — não em `database/` no host. Caminho real no host: `/var/lib/docker/volumes/dns-panel-rpz-data/_data/` e `/var/lib/docker/volumes/dns-panel-rpz-backups/_data/` (confirme com `docker volume inspect dns-panel-rpz-data`).

1. Backup manual imediato antes de qualquer coisa:
   ```bash
   docker compose exec app sh -c 'sqlite3 /data/database.sqlite ".backup /data/pre-incidente-$(date +%Y%m%d-%H%M%S).bak"'
   ```
2. Ver backups automáticos (diário, retém 14 dias em `/backups`, ver serviço `backup` no `compose.yml` / [`docker/backup.sh`](docker/backup.sh)):
   ```bash
   docker compose exec backup ls -la /backups
   ```
3. Restaurar um backup:
   ```bash
   docker compose stop app queue scheduler external-sync backup   # evita escrita durante a restauracao
   docker run --rm -v dns-panel-rpz-data:/data -v dns-panel-rpz-backups:/backups alpine \
     cp /backups/database.sqlite.auto-AAAAMMDD-HHMMSS.bak /data/database.sqlite
   docker compose start app queue scheduler external-sync backup
   ```
4. Integridade do arquivo atual, sem restaurar nada:
   ```bash
   docker compose exec app sqlite3 /data/database.sqlite "PRAGMA integrity_check;"
   ```

## Servidor perdido / backup local também sumiu (restaurar do R2)

Se o servidor inteiro se perdeu (não é só o banco — disco morto, VPS apagada) e o backup externo pro Cloudflare R2 estava ativo (`/configuracoes` → aba Backup), o backup mais recente está lá fora, independente do que aconteceu aqui.

1. No servidor novo, depois de reinstalado o painel (ver `README.md`), baixe o backup mais recente do bucket. Mais simples com o `rclone` ou o AWS CLI configurado pro endpoint R2 (`https://<account_id>.r2.cloudflarestorage.com`, região `auto`), ou direto pela UI do R2 no dashboard da Cloudflare (Armazenamento de objetos → bucket → baixar o `.bak` mais recente em `backups/`).
2. Copie o arquivo baixado pro volume `dns-panel-rpz-data` como `database.sqlite`:
   ```bash
   docker compose stop app queue scheduler external-sync backup
   docker run --rm -v dns-panel-rpz-data:/data -v "$(pwd)":/restore alpine \
     cp /restore/database.sqlite.auto-AAAAMMDD-HHMMSS.bak /data/database.sqlite
   docker compose start app queue scheduler external-sync backup
   ```
3. Confira integridade e se os dados batem com o esperado antes de liberar o painel pros clientes: `docker compose exec app sqlite3 /data/database.sqlite "PRAGMA integrity_check;"`.
4. Reconfigure as credenciais do R2 em `/configuracoes` → Backup assim que possível — elas não vêm no backup do banco (o Access Key/Secret ficam salvos na tabela `settings` **desse mesmo banco**, então se o banco restaurado já tinha isso configurado, já volta funcionando sozinho).

## Disco cheio / quase cheio

Healthcheck já alerta a partir de 85% (`/seguranca`). Se chegou a esse ponto:

1. `df -h /`
2. Suspeitos usuais: volume `dns-panel-rpz-backups` (deveria auto-limpar após 14 dias — `BACKUP_KEEP_DAYS` no `compose.yml`, confirme que o container `backup` está rodando: `docker compose ps backup`), logs do Docker em `/var/lib/docker/containers/*/*-json.log` (rotação já embutida via `logging.options` no `compose.yml`, `max-size 10m` / `max-file 3` por container — confirme que nenhum container tem `logging` fora do padrão), `storage/framework/views` dentro do volume `dns-panel-rpz-storage` (cache de blade, seguro limpar: `docker compose exec app php artisan view:clear`).
3. Não delete nada nos volumes `dns-panel-rpz-data` ou `dns-panel-rpz-backups` sem ter certeza de qual é qual — confira datas antes (`docker compose exec backup ls -la /backups`).

## Certificado TLS expirando ou expirado

Renovação é automática (timer `dns-panel-rpz-certbot-renew.timer`, roda 2x/dia via container `certbot/certbot`, ver [`deploy/scripts/dns-panel-rpz-certbot-renew`](deploy/scripts/dns-panel-rpz-certbot-renew)). O certificado emitido fica em `certbot/conf/live/rpz.trevizamnetwork.com.br/`; depois de renovado é validado e copiado pro nginx de borda em `/etc/nginx/tls/rpz.trevizamnetwork.com.br/` por [`deploy/scripts/dns-panel-rpz-deploy-certificate`](deploy/scripts/dns-panel-rpz-deploy-certificate), que também roda `nginx -t` + reload com rollback automático se der problema.

Se o healthcheck alertar (`health.cert_expiring` ou `health.cert_unreadable`) ou o site começar a dar erro de certificado:

```bash
systemctl status dns-panel-rpz-certbot-renew.timer     # timer ativo?
journalctl -u dns-panel-rpz-certbot-renew.service -n 50 --no-pager   # log da última tentativa
systemctl start dns-panel-rpz-certbot-renew.service     # forca renovacao/redeploy na hora
openssl x509 -in /etc/nginx/tls/rpz.trevizamnetwork.com.br/cert.pem -noout -enddate   # confere validade do que esta publicado
```

## Login SSH travado / suspeita de brute-force

- `/seguranca` no painel mostra IPs banidos pelo fail2ban em tempo real.
- `fail2ban-client status sshd` — lista bans ativos direto no servidor.
- Desbanir um IP (se foi falso positivo, ex: seu próprio IP mudou):
  ```bash
  fail2ban-client set sshd unbanip <IP>
  ```
- **Se você mesmo ficar sem acesso SSH**: existe uma chave SSH (ed25519) instalada em `~/.ssh/authorized_keys` do root, testada e funcional, como alternativa à senha (ver README, seção "Segurança do host"). Login por senha continua habilitado por decisão consciente — não foi desativado.

## Fazer um deploy

Não há deploy automático: o código roda a partir do diretório do repositório no servidor, dentro de imagens Docker.

```bash
git status                                   # árvore limpa; testes verdes antes de subir
docker compose exec app php artisan backup:run   # OBRIGATÓRIO se houver migration nova (backup local + R2)

docker compose build app nginx               # 'app' = código PHP/views; 'nginx' = arquivos estáticos (public/assets/app.css)
RPZ_ENV_FILE=/etc/dns-panel-rpz/app.env docker compose --profile cutover up -d
docker compose restart nginx                 # o app recriado troca de IP interno; sem isso o site cai com 502

docker compose exec app php artisan migrate --force   # só se houver migration nova; o init.sh NÃO roda migrate
```

Confira depois:

```bash
curl -sk -o /dev/null -w "%{http_code}\n" https://rpz.trevizamnetwork.com.br/login          # 200
docker exec dns-panel-rpz-app-1 sh -c "grep -c '<algo-que-so-existe-no-codigo-novo>' resources/views/<arquivo>"
curl -sk https://rpz.trevizamnetwork.com.br/assets/app.css | grep -c '<regra-nova>'          # se mexeu em CSS
git push origin feat/dockerize-rpz
```

Pegadinhas:
- **Mudou CSS e nada mudou na tela?** Faltou `docker compose build nginx` — os estáticos ficam na imagem do nginx, não na do app. Depois peça Ctrl+F5 (cache do navegador).
- **Mudou só view/texto?** `build app` basta, mas rodar os dois é inofensivo.
- **Migration nova**: leia o `docs/CHANGELOG.md`; tudo o que só adiciona coluna/índice é seguro, mas faça o backup antes mesmo assim.
- Para testar uma view como um usuário sem abrir o navegador: `docker compose exec -T -e HOME=/tmp app php artisan tinker` com `view()->share('errors', new Illuminate\Support\ViewErrorBag); Auth::login($user); app(Controller::class)->metodo()->render();` (só leitura).

## Reverter um deploy

Não há deploy automático — toda mudança é manual (`git pull` + rebuild das imagens). Pra reverter:

```bash
git log --oneline -10          # acha o commit bom anterior
git diff <commit-bom> --stat   # confere o que mudou desde entao
git checkout <commit-bom> -- <arquivo-especifico>   # reverte so o arquivo problematico
# ou, se for tudo:
git reset --hard <commit-bom>  # CUIDADO: descarta mudancas locais nao commitadas

docker compose build app nginx   # reconstroi as imagens com o codigo revertido
RPZ_ENV_FILE=/etc/dns-panel-rpz/app.env docker compose --profile cutover up -d   # recria os containers com a imagem nova
docker compose restart nginx     # IMPORTANTE: o container app recriado troca de IP interno; sem isso o nginx
                                  # continua apontando pro IP antigo e o site cai com 502 ate reiniciar o nginx
```

Depois de qualquer reversão de código: `docker compose exec app php artisan migrate:status` pra conferir se alguma migration ficou "à frente" do código revertido (isso pode quebrar o schema — nesse caso, restaurar backup do banco em vez de só reverter código).

## Onde tudo mora

| O quê | Onde |
|---|---|
| Logs da aplicação/queue/scheduler/external-sync | `docker compose logs <serviço>` (LOG_CHANNEL=stderr, não tem mais arquivo `storage/logs/*.log`) |
| Logs do healthcheck | `journalctl -u dns-panel-rpz-healthcheck.service` |
| Logs do Certbot/renovação | `journalctl -u dns-panel-rpz-certbot-renew.service`, ou `certbot/logs/` dentro do repo |
| Banco (SQLite) | volume `dns-panel-rpz-data` (`/data/database.sqlite` dentro dos containers) |
| Backups do banco | volume `dns-panel-rpz-backups` (`docker compose exec backup ls -la /backups`) |
| Certificado TLS ativo (servido pelo nginx de borda) | `/etc/nginx/tls/rpz.trevizamnetwork.com.br/` |
| Certificado TLS emitido pelo Certbot (origem) | `certbot/conf/live/rpz.trevizamnetwork.com.br/` (dentro do repo, não versionado) |
| Config real de infra (nginx/systemd/fail2ban/scripts) | `/etc/nginx`, `/etc/systemd/system`, `/etc/fail2ban`, `/usr/local/sbin/dns-panel-rpz-*` — cópias versionadas em [`deploy/`](deploy/) |
| Histórico de ações do sistema | `/auditoria` no painel |
| Ameaças de segurança (SSH banido pelo fail2ban, saúde do servidor) | `/seguranca` no painel |
| CI (roda testes a cada push) | GitHub Actions, repo `TrevizamNetwork001/dns-panel-rpz` |
