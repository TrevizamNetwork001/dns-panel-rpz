# deploy/

Cópias de referência da configuração de infraestrutura do servidor `paineldns`. **Não são lidas automaticamente pelo servidor** — são só documentação versionada do estado esperado.

> **Arquitetura desde a migração para Docker (set/2026):** a aplicação (app/queue/scheduler/external-sync/backup) roda em containers via `docker compose` (ver [`compose.yml`](../compose.yml) na raiz). Só ficam direto no host: o nginx de borda (TLS/reverse-proxy pro container nginx em `127.0.0.1:8082`), o Certbot, o fail2ban e os timers systemd de healthcheck/renovação de certificado. **Não existe mais PHP nem php-fpm instalado no host** — qualquer `artisan` precisa rodar via `docker compose exec app php artisan ...`.

Se você editar a configuração real no servidor, lembre de copiar a mudança pra cá também:

```bash
cp /etc/nginx/sites-available/rpz-gateway.conf deploy/nginx/rpz-gateway.conf
cp /etc/systemd/system/dns-panel-rpz-certbot-renew.* deploy/systemd/
cp /etc/systemd/system/dns-panel-rpz-healthcheck.* deploy/systemd/
cp /etc/fail2ban/jail.local deploy/fail2ban/jail.local
cp /etc/fail2ban/action.d/dns-panel-hook.conf deploy/fail2ban/dns-panel-hook.conf
cp /usr/local/sbin/dns-panel-rpz-security-log-ban deploy/scripts/dns-panel-rpz-security-log-ban
cp /usr/local/sbin/dns-panel-rpz-healthcheck-docker deploy/scripts/dns-panel-rpz-healthcheck-docker
```

## Conteúdo

- `nginx/rpz-gateway.conf` — vhost real em produção (`/etc/nginx/sites-available/rpz-gateway.conf`) do domínio `rpz.trevizamnetwork.com.br` (HTTP→HTTPS + certificado do Certbot, proxy pro container nginx em `127.0.0.1:8082`). Renomeado durante a migração — chamava-se `dns-panel-rpz-domain` antes.
- `nginx/dns-blocked-page.conf` — vhost `default_server` (catch-all) que serve a página estática "Esta página está bloqueada" (`/opt/dns-blocked-page`, restaurado do backup da migração) para qualquer Host desconhecido — usado pelo modo `redirect` do RPZ. **Não está ativo em produção**: o `default_server` da porta 443 nesse host está com `ircenter-gateway.conf` (outro site), não com este. Precisa de uma decisão de infra antes de habilitar — ver comentário no topo do arquivo.
- `scripts/dns-panel-rpz-healthcheck-docker` — wrapper chamado pelo timer de healthcheck; monta `/etc/nginx/tls/...` como `/etc/letsencrypt/live/...` dentro de um container descartável (`docker compose run`) e roda `php artisan health:check`.
- `scripts/dns-panel-rpz-security-log-ban` — wrapper chamado pelo hook do fail2ban; roda `php artisan security:log-ban` dentro do container `app` já em execução (`docker compose exec`), já que não há PHP no host.
- `systemd/dns-panel-rpz-healthcheck.service` + `.timer` — healthcheck (disco, certificado TLS, disponibilidade do site) a cada 30min, rodando como root, via `scripts/dns-panel-rpz-healthcheck-docker`.
- `systemd/dns-panel-rpz-certbot-renew.service` + `.timer` — renovação do certificado Certbot e cópia para `/etc/nginx/tls/rpz.trevizamnetwork.com.br/` (caminho que o nginx de borda e o healthcheck enxergam).
- `fail2ban/jail.local` — jail do SSH (backend systemd/journal, 5 tentativas em 10min → ban de 1h) + hook customizado.
- `fail2ban/dns-panel-hook.conf` — action do fail2ban que chama `scripts/dns-panel-rpz-security-log-ban` a cada ban/unban, alimentando a página `/seguranca` do painel.

### O que **não** existe mais desde a dockerização (removido nessa migração)

- Backup, sync de listas externas e worker de fila **não são mais systemd units** — viraram os serviços `backup`, `external-sync` e `queue` do `compose.yml` (perfil `cutover`), com loop próprio via `docker/automation-loop.sh` (intervalo configurado em `AUTOMATION_INTERVAL`).
- `logrotate/dns-panel-rpz` foi removido: a aplicação roda com `LOG_CHANNEL=stderr`, então os logs vão pro `stdout`/`stderr` do container, capturados pelo driver `json-file` do Docker (rotação já embutida no `compose.yml`: `max-size 10m`, `max-file 3`). `storage/logs/*.log` não é mais usado. Ver logs com `docker compose logs -f <serviço>`.
- `php8.4-fpm` não existe mais no host — foi substituído pelo container `app` (PHP-FPM Alpine, ver [`Dockerfile`](../Dockerfile)).
