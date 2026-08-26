# deploy/

Cópias de referência da configuração de infraestrutura do servidor `paineldns`. **Não são lidas automaticamente pelo servidor** — são só documentação versionada do estado esperado.

Se você editar a configuração real no servidor, lembre de copiar a mudança pra cá também:

```bash
cp /etc/nginx/sites-available/dns-panel-rpz-domain deploy/nginx/dns-panel-rpz-domain.conf
cp /etc/nginx/sites-available/dns-blocked-page deploy/nginx/dns-blocked-page.conf
cp /etc/systemd/system/dns-panel-rpz-backup.* deploy/systemd/
cp /etc/systemd/system/dns-panel-rpz-external-sync.* deploy/systemd/
cp /etc/systemd/system/dns-panel-rpz-healthcheck.* deploy/systemd/
cp /etc/fail2ban/jail.local deploy/fail2ban/jail.local
cp /etc/fail2ban/action.d/dns-panel-hook.conf deploy/fail2ban/dns-panel-hook.conf
cp /etc/logrotate.d/dns-panel-rpz deploy/logrotate/dns-panel-rpz
```

## Conteúdo

- `nginx/dns-panel-rpz-domain.conf` — vhost do domínio `rpz.trevizamnetwork.com.br` (HTTP→HTTPS + certificado gerenciado pelo Certbot).
- `nginx/dns-blocked-page.conf` — vhost `default_server` (catch-all) que serve a página estática "Esta página está bloqueada" (`/opt/dns-blocked-page`) para qualquer Host desconhecido — usado pelo modo `redirect` do RPZ. Reutiliza o certificado do Certbot de `rpz.trevizamnetwork.com.br`.
- `systemd/dns-panel-rpz-backup.service` + `.timer` — backup diário do SQLite (03:30, retém 14 dias em `database/backups/`).
- `systemd/dns-panel-rpz-external-sync.service` + `.timer` — sincroniza todas as listas externas ativas a cada 6h (`php artisan external:sync`, URL/formato configurável por lista).
- `systemd/dns-panel-rpz-queue.service` — worker persistente da fila usada pela extração de PDFs ANATEL e pelo progresso da interface.
- `fail2ban/jail.local` — jail do SSH (backend systemd/journal, 5 tentativas em 10min → ban de 1h) + hook customizado.
- `fail2ban/dns-panel-hook.conf` — action do fail2ban que chama `php artisan security:log-ban` a cada ban/unban, alimentando a página `/seguranca` do painel.
- `systemd/dns-panel-rpz-healthcheck.service` + `.timer` — healthcheck (disco, certificado TLS, disponibilidade do site) a cada 30min, rodando como root (`php artisan health:check`).
- `logrotate/dns-panel-rpz` — rotação semanal dos logs em `storage/logs/*.log`, retém 8 semanas, `copytruncate` (não precisa sinalizar o PHP-FPM).
