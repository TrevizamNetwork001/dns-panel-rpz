# deploy/

Cópias de referência da configuração de infraestrutura do servidor `paineldns` (45.239.157.239). **Não são lidas automaticamente pelo servidor** — são só documentação versionada do estado esperado.

Se você editar a configuração real no servidor, lembre de copiar a mudança pra cá também:

```bash
cp /etc/nginx/sites-available/dns-panel-rpz-domain deploy/nginx/dns-panel-rpz-domain.conf
cp /etc/systemd/system/dns-panel-rpz-backup.* deploy/systemd/
```

## Conteúdo

- `nginx/dns-panel-rpz-domain.conf` — vhost do domínio `rpz.trevizamnetwork.com.br` (HTTP→HTTPS + certificado gerenciado pelo Certbot).
- `systemd/dns-panel-rpz-backup.service` + `.timer` — backup diário do SQLite (03:30, retém 14 dias em `database/backups/`).
