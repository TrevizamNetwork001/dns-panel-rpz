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
   - Zonefile vazio de domínios → nenhuma lista ativa vinculada, ou listas sem domínios ativos.
3. Valide a sintaxe do zonefile gerado (precisa de `bind9-utils` instalado):
   ```bash
   curl -s "https://.../rpz/{token}.zone" > /tmp/check.zone
   named-checkzone rpz.trevizamnetwork.com.br /tmp/check.zone
   ```
4. Do lado do cliente: confirmar que o bloco `rpz:` no `unbound.conf` dele está **depois** do bloco `server:` (antes, se usar `hyperlocal`), e rodar `unbound-checkconf` antes de qualquer restart.

## Lista externa não atualiza

1. `docker compose ps external-sync` — container ativo? (roda em loop próprio, sincroniza a cada `AUTOMATION_INTERVAL` segundos, padrão 6h — ver `compose.yml`)
2. `docker compose exec app php artisan external:sync` — roda todas as fontes externas na mão e mostra o resultado individual de cada uma.
3. Motivo comum de abort (por design, não é bug): um feed retornou menos de 100 domínios — a proteção evita esvaziar a lista quando a fonte está fora do ar ou muda de formato. Veja `docker compose logs external-sync`.
4. Lista pode estar pausada manualmente: confira `sync_ativo` em `/listas` (badge "Pausar sync" vira "Reativar sync" quando pausada).

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
