# Implantação Docker do DNS Panel RPZ

## Arquitetura e auditoria

A imagem `app` usa PHP 8.4-FPM Alpine e instala as extensões exigidas pelo Laravel e pelo código: `bcmath`, `ctype`, `curl`, `dom/xml`, `fileinfo`, `intl`, `mbstring`, `openssl`, `pcntl`, `pdo_sqlite`, `sockets`, `tokenizer` e `zip`, além de OPcache. O build instala as dependências de compilação necessárias para `mbstring` (`oniguruma-dev`), SQLite/PDO (`sqlite-dev`), XML/DOM (`libxml2-dev`) e demais extensões, valida `dom`, `simplexml` e `xml` com `php -m`, e remove o pacote virtual `.build-deps` antes da imagem final. O Composer instala o `composer.lock` com `--no-dev --optimize-autoloader`; `.env`, bancos, uploads e segredos são excluídos do contexto.

O pipeline ANATEL é utilizado por `ProcessAnatelImport`: a mesma imagem inclui Python e `pdfplumber==0.11.7`, com `ANATEL_PYTHON_BIN=/opt/anatel-venv/bin/python`. A fila usa o banco SQLite. O scheduler Laravel executa o RBL a cada seis horas. A sincronização externa e o backup são loops isolados de seis e 24 horas. Eles, a fila e o scheduler pertencem ao profile `cutover` e ficam desligados no preparo.

| Serviço | Função | Exposição |
|---|---|---|
| `init` | cria diretórios e corrige permissões dos volumes; termina em seguida | nenhuma |
| `app` | PHP-FPM como `www-data` | somente rede Compose, porta 9000 |
| `nginx` | HTTP interno sem root | `127.0.0.1:8082` apenas |
| `queue` | importações ANATEL | profile `cutover`, sem porta |
| `scheduler` | RBL (`schedule:work`) | profile `cutover`, sem porta |
| `external-sync` | listas externas a cada 6 h | profile `cutover`, sem porta |
| `backup` | `.backup` SQLite a cada 24 h | profile `cutover`, sem porta |

Volumes exclusivos: `dns-panel-rpz-data` (`/data/database.sqlite`, incluindo os arquivos WAL/SHM no mesmo diretório), `dns-panel-rpz-storage`, `dns-panel-rpz-cache` e `dns-panel-rpz-backups`. Não há PostgreSQL, Redis ou FPM publicado. Os logs de containers usam `json-file`, 10 MB × 3; Laravel deve usar `LOG_CHANNEL=stderr`. Os limites somados são adequados a 2 vCPU/4 GB, e somente o inicializador efêmero roda como root.

O healthcheck do FPM usa seu endpoint de ping; o do Nginx atravessa FastCGI e a rota Laravel `/up`. O comando legado `health:check` inspeciona certificado e URL pública e grava no banco, portanto deve ser tratado como automação operacional do host novo, depois do TLS, e não como healthcheck Docker. A fila usa `--timeout=180`; mantenha `DB_QUEUE_RETRY_AFTER=300` ou outro valor seguramente maior que 180 para evitar que um job seja liberado antes de o worker terminar.

## Preparação da VPS nova

Instale Docker Engine e o plugin Compose pelos repositórios oficiais da distribuição. Clone esta branch em um diretório dedicado. Não copie o `.env` antigo inteiro. Confirme que `127.0.0.1:8082` está livre e que nenhum projeto IRCENTER usa os nomes `dns-panel-rpz-*`.

Crie o arquivo fora do repositório, por exemplo `/etc/dns-panel-rpz/app.env`, proprietário `root`, modo `0600`. Copie somente os nomes necessários de `.env.docker.example`, gere uma chave com `docker run --rm dns-panel-rpz-app:local php artisan key:generate --show`, preencha segredos sem exibi-los e mantenha `APP_DEBUG=false`. Use-o assim:

```bash
export RPZ_ENV_FILE=/etc/dns-panel-rpz/app.env
docker compose build
docker compose config >/dev/null
docker compose up -d init app nginx
```

Depois do build real na VPS nova, comprove os módulos e a remoção das dependências de compilação:

```bash
docker compose run --rm --no-deps app php -m | grep -E '^(dom|mbstring|pdo_sqlite|simplexml|xml)$'
docker compose run --rm --no-deps app sh -c 'apk info -e .build-deps || true'
```

O `compose.yml` não inicia migrations. Antes de apontar tráfego, confira `docker compose ps`, `docker compose logs --tail=100 app nginx` e `curl -fsS http://127.0.0.1:8082/up`.

## Restauração do SQLite e storage

Pare somente os containers da VPS nova antes de restaurar. Uma restauração offline evita separar o banco de seus WAL/SHM. Valide o backup na origem com `sqlite3 backup.sqlite 'PRAGMA integrity_check;'` e transfira-o por canal seguro, nunca pelo Git.

```bash
export RPZ_ENV_FILE=/etc/dns-panel-rpz/app.env
docker compose down
docker compose run --rm --user root --no-deps -v /caminho/seguro:/restore:ro app sh -c 'install -o www-data -g www-data -m 0660 /restore/database.sqlite /data/database.sqlite'
docker compose run --rm --no-deps app sqlite3 /data/database.sqlite 'PRAGMA integrity_check;'
docker compose up -d init app nginx
```

Nunca monte `database/` sobre o código. Para storage, restaure o conteúdo preservando a árvore em `storage`, especialmente `app/private/anatel` e `app/public`, e então rode `docker compose run --rm init`.

Rode migrations apenas após backup e revisão:

```bash
docker compose run --rm app php artisan migrate --force
```

## Validação antes do corte

Sem alterar DNS, teste diretamente o backend:

```bash
curl -fsS -H 'Host: rpz.trevizamnetwork.com.br' http://127.0.0.1:8082/up
curl --resolve rpz.trevizamnetwork.com.br:8082:127.0.0.1 \
  http://rpz.trevizamnetwork.com.br:8082/up
docker compose ps
docker compose config
docker compose port nginx 8080
```

Teste também um endpoint RPZ autenticado e o feed MikroTik com credenciais descartáveis/de homologação. Somente `127.0.0.1:8082` deve aparecer em `docker compose port nginx 8080`; os demais serviços não têm `ports`, inclusive PHP-FPM. Confirme que os limites aparecem no Compose final (`cpus`, `mem_limit` e `deploy.resources`) e, com os containers em execução, valide no Docker Engine da VPS nova:

```bash
docker inspect dns-panel-rpz-app-1 --format '{{.HostConfig.NanoCpus}} {{.HostConfig.Memory}}'
docker inspect dns-panel-rpz-nginx-1 --format '{{.HostConfig.NanoCpus}} {{.HostConfig.Memory}}'
```

## Nginx do host e página de bloqueio

Na VPS nova, o virtual host exclusivo de `rpz.trevizamnetwork.com.br` termina TLS e faz proxy para `http://127.0.0.1:8082`, preservando `Host`, `X-Real-IP`, `X-Forwarded-Proto` e porta. Para impedir spoofing, o Nginx do host deve sobrescrever `X-Forwarded-For` com o IP que ele observou em `$remote_addr`, em vez de aceitar uma cadeia enviada pelo cliente. O Nginx do container acrescenta o IP do proxy interno e o Laravel confia somente nos proxies definidos por `TRUSTED_PROXIES` (`loopback`, RFC1918 e ULA por padrão; ajuste para os CIDRs internos reais se a bridge da VPS for mais restrita). Certbot e as portas 80/443 permanecem no host. Não use `default_server` e não modifique o gateway do IRCENTER.

Os endpoints RPZ/MikroTik continuam no Laravel e os estáticos no Nginx do container. No modo `redirect`, as respostas RPZ apontam para o alvo configurado no painel. `/opt/dns-blocked-page` é uma aplicação estática separada: migre-a separadamente e publique-a em um hostname/IP explícito no Nginx do host. Não reutilize o `default_server` legado, pois isso capturaria tráfego do IRCENTER. Atualize o alvo de redirect somente durante um corte aprovado e após testar a página.

Exemplo de bloco do host (instalar manualmente depois da revisão):

```nginx
server {
    listen 443 ssl;
    server_name rpz.trevizamnetwork.com.br;
    # ssl_certificate e ssl_certificate_key gerenciados no host
    location / {
        proxy_pass http://127.0.0.1:8082;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $remote_addr;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Port $server_port;
    }
}
```

Com o bloco acima, valide que o Laravel enxerga o IP público verdadeiro e gera URLs HTTPS. O teste automatizado `TrustedProxyTest` cobre a cadeia `cliente -> Nginx host -> Nginx container -> PHP-FPM/Laravel`; na VPS, acrescente um teste operacional pelo vhost TLS, a partir de uma origem autorizada/de homologação, e confira o IP gravado no log de sincronização do servidor RPZ:

```bash
curl -fsS -H 'X-Forwarded-For: 198.51.100.200' https://rpz.trevizamnetwork.com.br/rpz/SLUG.zone
```

O header falso acima não deve prevalecer, porque o Nginx do host sobrescreve `X-Forwarded-For` com `$remote_addr`. Confira nos logs e no registro de sincronização do servidor RPZ que `request()->ip()` corresponde ao IP público real do cliente autorizado, e que links gerados por `url()`/`asset()` começam com `https://rpz.trevizamnetwork.com.br`.

## Corte, backup e rollback

Depois de restaurar, migrar, validar e congelar escritas na origem, ative as automações explicitamente:

```bash
docker compose --profile cutover up -d
docker compose ps
```

Evite dois workers/schedulers ativos contra cópias divergentes. O backup manual consistente é:

```bash
docker compose run --rm --no-deps backup /usr/local/bin/rpz-backup
docker compose run --rm --no-deps app sqlite3 /data/database.sqlite 'PRAGMA integrity_check;'
```

Para rollback antes de novas escritas, retire a VPS nova do DNS/proxy e volte o tráfego à antiga. Se houve escrita na nova, primeiro pare o profile `cutover`, faça `.backup`, transfira e restaure de modo offline na antiga com um plano aprovado; não sobreponha bancos vivos. A VPS antiga deve permanecer intacta até expirar a janela de rollback.

Diagnóstico útil:

```bash
docker compose ps
docker compose logs --tail=200 app nginx queue scheduler external-sync backup
docker compose exec app php -v
docker compose exec app php -m
docker compose exec app php artisan about
docker compose exec app php artisan queue:failed
docker compose exec app php artisan schedule:list
docker compose exec app sqlite3 /data/database.sqlite 'PRAGMA journal_mode; PRAGMA integrity_check;'
docker compose exec nginx test -L /var/www/html/public/storage
```
