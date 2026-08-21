# deploy/

Cópias de referência da configuração de infraestrutura do servidor `paineldns` (45.239.157.239). **Não são lidas automaticamente pelo servidor** — são só documentação versionada do estado esperado.

Se você editar a configuração real no servidor, lembre de copiar a mudança pra cá também:

```bash
cp /etc/nginx/sites-available/dns-panel-rpz-domain deploy/nginx/dns-panel-rpz-domain.conf
cp /etc/nginx/sites-available/dns-blocked-page deploy/nginx/dns-blocked-page.conf
cp /etc/systemd/system/dns-panel-rpz-backup.* deploy/systemd/
cp /etc/systemd/system/dns-panel-rpz-urlhaus.* deploy/systemd/
cp /etc/fail2ban/jail.local deploy/fail2ban/jail.local
cp /etc/fail2ban/action.d/dns-panel-hook.conf deploy/fail2ban/dns-panel-hook.conf
```

## Conteúdo

- `nginx/dns-panel-rpz-domain.conf` — vhost do domínio `rpz.trevizamnetwork.com.br` (HTTP→HTTPS + certificado gerenciado pelo Certbot).
- `nginx/dns-blocked-page.conf` — vhost `default_server` (catch-all) que serve a página estática "Esta página está bloqueada" (`/opt/dns-blocked-page`) para qualquer Host desconhecido — usado pelo modo `redirect` do RPZ. Reutiliza o certificado do Certbot de `rpz.trevizamnetwork.com.br`.
- `systemd/dns-panel-rpz-backup.service` + `.timer` — backup diário do SQLite (03:30, retém 14 dias em `database/backups/`).
- `systemd/dns-panel-rpz-urlhaus.service` + `.timer` — sincronização da lista externa URLhaus a cada 6h (`php artisan urlhaus:sync`).
- `fail2ban/jail.local` — jail do SSH (backend systemd/journal, 5 tentativas em 10min → ban de 1h) + hook customizado.
- `fail2ban/dns-panel-hook.conf` — action do fail2ban que chama `php artisan security:log-ban` a cada ban/unban, alimentando a página `/seguranca` do painel.
