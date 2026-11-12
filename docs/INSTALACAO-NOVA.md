# Instalar um painel RPZ independente com um único `.sh`

Este procedimento cria **outro painel**, com banco, usuários, chave e volumes próprios. É destinado a uma VPS nova. Não migra nem modifica o painel já existente da Trevizam.

O arquivo de entrada é [`scripts/install-panel.sh`](../scripts/install-panel.sh). Ele baixa o projeto e usa `compose.install.yml`; não depende das imagens incrementais do servidor atual.

## 1. Preparar a VPS e o DNS

- Use Linux Debian ou Ubuntu com acesso root/sudo e acesso à internet. A instalação automática de Docker usa o repositório oficial da distribuição correspondente. A validação do cenário foi feita em Linux amd64 com Docker; a instalação de pacotes via apt segue o procedimento oficial do Docker.
- Reserve espaço para Docker, imagens, banco e backups. Para começar, prefira 4 GB de RAM e 10 GB livres; o consumo real depende das listas e dos clientes. O build também consome memória.
- Separe dois nomes **irmãos**, por exemplo:

  | Nome | Função |
  | --- | --- |
  | `painel.seudominio.com.br` | Interface administrativa e nome lógico da zona |
  | `feeds.seudominio.com.br` | Downloads RPZ e MikroTik |

- Crie registros DNS A apontando ambos os nomes para o IPv4 da VPS. Publique AAAA somente se o IPv6 realmente funcionar.
- Use DNS sem proxy inicialmente, inclusive na Cloudflare. Um proxy na frente exige configuração explícita de proxies confiáveis para preservar os IPs usados nas ACLs.
- Libere **TCP 80 e 443** no firewall da VPS e nas regras do provedor. Preserve seu acesso SSH. O instalador não desliga serviços nem altera regras de firewall existentes.
- As portas 80/443 precisam estar livres para o Caddy. Não instale sobre um servidor web existente sem planejar o encaminhamento.

**Não use** `feeds.painel.seudominio.com.br`: o hostname de download deve ficar fora da zona `painel.seudominio.com.br`. O instalador rejeita essa combinação para evitar problemas na primeira busca do Unbound.

## 2. Baixar e executar somente o instalador

```bash
curl -fSL --retry 3 \
  https://raw.githubusercontent.com/TrevizamNetwork001/dns-panel-rpz/updates/rpz-2026-10-28/scripts/install-panel.sh \
  -o install-panel.sh
sudo bash install-panel.sh
```

Se já estiver como root, use `bash install-panel.sh`. Se `curl` não existir, baixe esse único arquivo em outro computador e envie à VPS, ou instale `curl` pelo gerenciador do sistema.

O instalador pergunta:

1. Domínio do painel.
2. Domínio dos feeds.
3. E-mail para os certificados HTTPS.
4. E-mail do primeiro administrador.
5. Senha do administrador e confirmação, sem mostrar a senha. Mínimo de 12 caracteres.

O código fica em `/opt/rpz-panel`. O diretório pode ser alterado com `RPZ_INSTALL_DIR` antes da primeira execução.

O repositório precisa estar acessível. Se estiver privado, configure o acesso Git da VPS e passe uma URL SSH autorizada em `RPZ_REPOSITORY_URL`. Não coloque tokens dentro do script ou da URL registrada no Git.

## 3. O que o `.sh` faz

- Instala Docker Engine, Buildx e Compose quando Docker ainda não existe em Debian/Ubuntu.
- Usa o Docker existente quando ele já tem Compose e um daemon acessível; não substitui uma instalação existente incompatível.
- Baixa a branch `updates/rpz-2026-10-28` e constrói PHP, dependências e assets a partir do código. Não usa imagens privadas do ambiente antigo.
- Cria `.env.install`, com uma `APP_KEY` aleatória e exclusiva; o arquivo fica com permissão 600.
- Inicializa volumes próprios e aplica as migrations em um banco SQLite novo.
- Cria o primeiro administrador, sem usuário/senha padrão e sem executar o seeder de demonstração.
- Inicia aplicação, servidor web, fila, agendador, sincronização externa e backup. Recarrega o nginx após recriar o PHP, evitando encaminhamento ao IP antigo e erro 502.
- Ativa Caddy com HTTPS automático e renovação dos certificados para os dois domínios.
- Faz um backup inicial do SQLite e verifica o login HTTPS e o isolamento do hostname de feeds.

A saída final só informa **instalação concluída** quando essas verificações passam. Não há credenciais fixas nem senha impressa no terminal.

O hostname de feeds publica apenas `/rpz/*` e `/mikrotik/*`; `/login` e outras páginas administrativas nele retornam 404. O backend fica acessível somente em `127.0.0.1:18082`. Caddy usa a rede do host para preservar a origem IPv4/IPv6 dos clientes.

## 4. Primeiro acesso e primeiro cliente

1. Entre em `https://painel.seudominio.com.br` com o administrador criado.
2. Cadastre a empresa do cliente.
3. Cadastre uma licença ativa e válida para essa empresa, com capacidade para seus servidores.
4. Cadastre uma fonte/lista e adicione domínios, ou configure uma fonte externa/ANATEL conforme sua operação.
5. Cadastre o endpoint DNS, escolha a empresa e vincule as listas.
6. Em **Gerenciar IPs**, confira os IPs reais de saída do DNS do cliente e libere a ACL. Considere IPv4 e IPv6.
7. Copie a configuração oferecida nos detalhes do servidor, valide com `unbound-checkconf` e aplique no DNS do cliente.
8. Confira a primeira consulta no painel e teste o domínio canário no Unbound. Uma consulta de download no painel não prova, sozinha, que a zona foi carregada pelo DNS.

O painel nasce sem empresas, licenças, listas ou clientes fictícios. O instalador não modifica o Unbound dos clientes remotamente.

A distribuição inicial funciona por **download HTTPS**. Primary SOA/UDP e NOTIFY são recursos opcionais: ficam desligados, sem abrir UDP 53 ou reutilizar IDs/IPs do piloto antigo. Precisam de planejamento e validação próprios para o novo ambiente.

## 5. Verificar os serviços

```bash
sudo bash /opt/rpz-panel/scripts/install-panel.sh --check
```

Para diagnóstico manual:

```bash
cd /opt/rpz-panel
docker compose --env-file .env.install -f compose.install.yml --profile https ps
docker compose --env-file .env.install -f compose.install.yml logs --tail=100 app web edge queue
docker compose --env-file .env.install -f compose.install.yml exec -T app php artisan migrate:status
```

O comando `--check` verifica os seis serviços internos, a saúde de app/web e as respostas HTTP/HTTPS do painel. Não altera usuários nem reinstala o sistema.

## 6. Backup e recuperação

O instalador faz o primeiro backup e mantém um serviço que verifica, a cada hora, se o backup semanal está vencido. A retenção local padrão é 14 dias.

```bash
cd /opt/rpz-panel
docker compose --env-file .env.install -f compose.install.yml exec -T app php artisan backup:run
```

O backup SQLite fica no volume `backups`. **Ele não é uma cópia completa de todos os arquivos.** Para recuperação integral, preserve também:

- `.env.install`, especialmente a `APP_KEY` original;
- o volume `storage`, que contém arquivos da aplicação;
- a revisão do Git e as configurações da instalação;
- os volumes de certificados, ou a capacidade de emitir novos certificados.

Configure o Cloudflare R2 em **Configurações → Backup** para ter uma cópia externa do banco. Guarde a chave e os arquivos complementares de forma segura fora da VPS. Uma cópia somente no próprio disco não cobre perda da VPS.

Em uma restauração, mantenha a chave original. A geração automática de uma nova chave é apenas para uma instalação realmente nova.

## 7. Executar novamente e atualizar

É possível executar o mesmo `.sh` novamente: ele preserva `.env.install`, a chave, os volumes e o administrador. Antes de reconstruir uma instalação concluída, faz backup do banco, copia a configuração para `.recovery/install-backups` e mantém as imagens anteriores com tags `before-AAAAmmdd-HHMMSS`.

**Reexecutar não baixa atualizações automaticamente.** Para atualizar de forma explícita:

```bash
cd /opt/rpz-panel
git status --short
git pull --ff-only
sudo bash scripts/install-panel.sh
```

Resolva alterações locais antes do `git pull`. Leia as migrations da versão nova: backup do banco não torna uma alteração de schema automaticamente reversível.

As tags disponíveis para reversão ficam registradas em `.recovery/install-backups/release-*.txt`. Depois de escolher uma tag real e conferir a compatibilidade do banco, por exemplo:

```bash
cd /opt/rpz-panel
RPZ_RELEASE=before-AAAAMMDD-HHMMSS docker compose \
  --env-file .env.install -f compose.install.yml --profile https \
  up -d --no-build --force-recreate app web queue scheduler external-sync backup
```

Não invente a tag: substitua pelo valor de um arquivo de release existente. `init` também pode ser reexecutado pelo Compose; confirme a compatibilidade da versão antes de voltar código após migrations.

## 8. Integrações opcionais

- Telegram: configure bot/chat no painel.
- Cloudflare R2: configure credenciais e bucket no painel.
- E-mail: o padrão é `MAIL_MAILER=log`; não há envio externo até configurar um provedor.
- CAPTCHA do cadastro público: configure as chaves Turnstile se desejar habilitá-lo.
- Fontes externas: precisam ser cadastradas/ativadas; o instalador não baixa catálogos inventados.

Esses serviços exigem suas próprias credenciais e não são criados automaticamente em contas externas.

### Versão do PHP e modo de bloqueio

O build usa PHP 8.4.27 e mantém `session.upload_progress.enabled=0`. O PHP publicou essa versão como correção das CVE-2026-109618 e CVE-2026-109619; veja o [comunicado oficial](https://www.php.net/). Isso não dispensa futuras atualizações do runtime e das dependências.

Use NXDOMAIN como modo inicial de bloqueio. A página de bloqueio por redirecionamento exige preparação própria do destino e tem limitações em HTTPS; o instalador não configura uma interceptação de sites de terceiros.

## 9. Problemas comuns

| Sintoma | Verificação |
| --- | --- |
| HTTPS não concluiu | Confira A/AAAA dos dois domínios, portas 80/443 e logs de `edge`. A emissão pública exige alcançar a VPS. |
| Porta ocupada | O instalador não encerra outros serviços. Libere a porta ou planeje um proxy existente. A porta interna pode ser definida com `RPZ_UPSTREAM_PORT` na primeira execução. |
| Feed retorna 403 | Confira empresa/servidor/licença ativos e ACL para o IP de saída real, inclusive IPv6. |
| Banco aparece vazio após mexer no Compose | Confira `RPZ_PROJECT_NAME` e os volumes. Outro nome de projeto cria outro conjunto de volumes. Não use `down -v` para corrigir isso. |
| Chave ausente/inválida | Restaure a `.env.install` original. Não gere outra chave para um banco existente. |
| Outro instalador ativo | Espere o processo terminar. Só remova `.install-lock` manualmente depois de confirmar que não há instalação em andamento. |
| Docker existente sem Compose | Instale um plugin compatível com o Docker instalado e execute novamente. O script não substitui esse Docker. |
| Repositório inacessível | Confira a URL, a branch e a autorização Git quando o repositório for privado. |

Não misture `compose.install.yml` com `compose.staging.yml`, `compose.preview.yml`, `compose.contabo.yml` ou o Compose legado de outra instalação.

## 10. Instalação não interativa e laboratório

Para automação, informe os domínios, e-mails e um arquivo de senha com permissão 600:

```bash
sudo env RPZ_PANEL_DOMAIN=painel.exemplo.com \
  RPZ_FEED_DOMAIN=feeds.exemplo.com RPZ_ACME_EMAIL=admin@exemplo.com \
  RPZ_ADMIN_EMAIL=admin@exemplo.com RPZ_ADMIN_PASSWORD_FILE=/root/senha-admin.txt \
  bash install-panel.sh
```

O conteúdo desse arquivo é lido pela entrada padrão; a senha não entra nos argumentos do processo. Remova ou armazene esse arquivo de forma segura após o uso.

`--source-dir DIRETORIO` usa um checkout existente. `--local-only` é exclusivamente para laboratório: sobe o backend em loopback e não ativa HTTPS público. Não é o modo de publicação para clientes.

## Validação e referências

A instalação foi exercitada com volumes novos, login real no navegador, geração/entrega RPZ, negação por ACL, backup com restauração/integridade e reexecução preservando configuração e administrador. Um Unbound real fez a primeira transferência HTTPS e respondeu NXDOMAIN para o domínio sintético bloqueado. A suíte PHP completa passou com 533 testes e 2.607 asserções; o instalador passou no ShellCheck. HTTPS foi verificado com CA interna no laboratório e o Caddyfile de produção foi validado. Isso não substitui a emissão ACME e o teste externo nos domínios reais de cada VPS.

O workflow `.github/workflows/install-smoke.yml` exercita a instalação e a reexecução em um runner com Docker, sem banco de produção. A preparação apt de uma VPS virgem e a emissão ACME pública dependem do sistema e DNS de destino; não foram apresentadas como teste feito em uma VPS externa.

- [Instalação oficial do Docker em Debian](https://docs.docker.com/engine/install/debian/)
- [Instalação oficial do Docker em Ubuntu](https://docs.docker.com/engine/install/ubuntu/)
- [HTTPS automático do Caddy](https://caddyserver.com/docs/automatic-https)
- [Reverse proxy do Caddy](https://caddyserver.com/docs/caddyfile/directives/reverse_proxy)
