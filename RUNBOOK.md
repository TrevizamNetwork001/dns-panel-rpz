# Runbook de incidentes — DNS Panel RPZ

Guia rápido pra quando algo dá errado em produção (`rpz.trevizamnetwork.com.br`). Comandos assumem SSH no servidor `paineldns`, dentro de `/opt/dns-panel-rpz`.

## Primeiro passo, sempre

```bash
curl -sI https://rpz.trevizamnetwork.com.br/up   # health route do Laravel
tail -50 storage/logs/laravel.log
sudo -u www-data php artisan health:check         # roda a checagem na hora, mostra o motivo
```

Olhe também `/seguranca` e `/auditoria` no painel — a maioria dos incidentes já deixa rastro lá antes de você precisar entrar por SSH.

---

## Site fora do ar (HTTP não responde / 502 / 500)

1. `systemctl status nginx php8.4-fpm` — algum dos dois caiu?
2. `sudo -u www-data php artisan health:check` — roda a checagem manualmente, mostra disco/certificado/site.
3. `tail -100 storage/logs/laravel.log` — erro de aplicação (query, permissão, config).
4. Se for 502: `journalctl -u php8.4-fpm --since "-10 min"` — PHP-FPM travado ou sem workers livres.
5. Restart seguro (não derruba sessões ativas de outros processos):
   ```bash
   systemctl restart php8.4-fpm
   systemctl reload nginx   # reload, nao restart -- evita drop de conexoes em andamento
   ```
6. Se nada disso resolver, confirme que não foi um deploy quebrado: `git log --oneline -5` e considere reverter (ver seção "Reverter um deploy").

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

1. `systemctl status dns-panel-rpz-external-sync.timer` — timer ativo?
2. `sudo -u www-data php artisan external:sync` — roda todas as fontes externas na mão e mostra o resultado individual de cada uma.
3. Motivo comum de abort (por design, não é bug): um feed retornou menos de 100 domínios — a proteção evita esvaziar a lista quando a fonte está fora do ar ou muda de formato. Veja `storage/logs/external-sync.log`.
4. Lista pode estar pausada manualmente: confira `sync_ativo` em `/listas` (badge "Pausar sync" vira "Reativar sync" quando pausada).

## Banco de dados corrompido ou dado errado

**Nunca edite o SQLite de produção direto sem backup antes.**

1. Backup manual imediato antes de qualquer coisa:
   ```bash
   sqlite3 database/database.sqlite ".backup 'database/backups/pre-incidente-$(date +%Y%m%d-%H%M%S).bak'"
   ```
2. Restaurar de um backup automático (diário, 03:30, retém 14 dias em `database/backups/database.sqlite.auto-*.bak`):
   ```bash
   ls -la database/backups/
   systemctl stop php8.4-fpm   # evita escrita durante a restauracao
   cp database/backups/database.sqlite.auto-AAAAMMDD-HHMMSS.bak database/database.sqlite
   chown devops:www-data database/database.sqlite
   systemctl start php8.4-fpm
   ```
3. Integridade do arquivo atual, sem restaurar nada:
   ```bash
   sqlite3 database/database.sqlite "PRAGMA integrity_check;"
   ```

## Disco cheio / quase cheio

Healthcheck já alerta a partir de 85% (`/seguranca`). Se chegou a esse ponto:

1. `df -h /`
2. Suspeitos usuais: `database/backups/` (deveria auto-limpar após 14 dias, confirme que o timer de backup está rodando), `storage/logs/*.log` (rotação semanal via `logrotate`, retém 8 semanas — confirme que não parou: `logrotate -d /etc/logrotate.d/dns-panel-rpz`), `storage/framework/views` (cache de blade, seguro limpar: `php artisan view:clear`).
3. Não delete `database/database.sqlite` nem nada em `database/backups/` sem ter certeza de qual é qual — confira datas antes.

## Certificado TLS expirando ou expirado

Renovação é automática via Certbot, mas se o healthcheck alertar (`health.cert_expiring`) ou o site começar a dar erro de certificado:

```bash
certbot certificates                       # ve validade atual
certbot renew --dry-run                    # testa sem aplicar
certbot renew                              # aplica de verdade
systemctl reload nginx
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

Não há deploy automático — toda mudança é manual (`git pull` ou arquivos copiados na mão + `chgrp www-data`). Pra reverter:

```bash
git log --oneline -10          # acha o commit bom anterior
git diff <commit-bom> --stat   # confere o que mudou desde entao
git checkout <commit-bom> -- <arquivo-especifico>   # reverte so o arquivo problematico
# ou, se for tudo:
git reset --hard <commit-bom>  # CUIDADO: descarta mudancas locais nao commitadas
```

Depois de qualquer reversão de código: `php artisan migrate:status` pra conferir se alguma migration ficou "à frente" do código revertido (isso pode quebrar o schema — nesse caso, restaurar backup do banco em vez de só reverter código).

## Onde tudo mora

| O quê | Onde |
|---|---|
| Logs da aplicação | `storage/logs/laravel.log` |
| Logs das listas externas | `storage/logs/external-sync.log` |
| Logs do healthcheck | `storage/logs/health-check.log` |
| Backups do banco | `database/backups/*.bak` |
| Config real de infra (nginx/systemd/fail2ban) | `/etc/nginx`, `/etc/systemd/system`, `/etc/fail2ban` — cópias versionadas em [`deploy/`](deploy/) |
| Histórico de ações do sistema | `/auditoria` no painel |
| Ameaças de segurança (SSH, saúde do servidor) | `/seguranca` no painel |
| CI (roda testes a cada push) | GitHub Actions, repo `TrevizamNetwork001/dns-panel-rpz` |
