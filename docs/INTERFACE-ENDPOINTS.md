# Interface de endpoints e servidores

Atualizado em 21/09/2026. Consolida a reorganização dos detalhes de servidores (admin e cliente), o gerenciamento de IPs e a modernização da listagem/cadastro/edição do admin.

## Escopo e limites

Alterações em Blade, CSS local aos partials, SVGs e JavaScript de apresentação. Controllers, rotas, models, banco, geração RPZ, sincronização, ACL e permissões não foram alterados. Não há migrations nem comandos remotos disparados pela interface. Os formulários mantêm rotas, métodos HTTP, CSRF e confirmações existentes.

A imagem fornecida pelo usuário orientou o acabamento. A pasta `referencias/` é material local, ignorado pelo Git; não é dependência da aplicação nem conteúdo publicado. Dados de demonstração e screenshots temporários também não são versionados.

## Detalhes do servidor — admin e cliente

- Cabeçalho com nome, empresa, **Sincronizar agora** (Unbound) e **Editar**.
- Faixa de status com SVGs para status cadastral, estado operacional, última consulta relativa, tipo de DNS e modo de bloqueio. Os dois perfis usam o mesmo componente visual. Os critérios existentes permanecem: sem consulta, atenção a partir de 2 dias e normal nos demais casos.
- **Listas vinculadas**: quantidade, até 3 tags e contador das restantes. **Ver listas** abre um `dialog` lateral com nome, descrição, tipo, ícone de categoria e ação de remover. A remoção mantém confirmação e endpoint DELETE.
- Fontes disponíveis para adicionar ficam no formulário de edição, não nos detalhes.
- O card separado **Acesso RPZ** foi removido. A URL copiável fica na configuração técnica, preservando a escolha da URL empresarial/fallback já existente. O botão de copiar configuração usa o snippet correspondente ao tipo DNS (Unbound ou MikroTik).
- **Configuração técnica** contém configuração do DNS e, no Unbound, **Solução de problemas**. São seções `<details>` fechadas inicialmente. Cada seção tem **× Fechar** ao final; recolher devolve foco ao título e o traz para a área visível.
- **Sincronizar agora** abre as instruções de `unbound-control auth_zone_transfer`; o usuário precisa executar o comando no DNS do cliente. O nome do botão não representa uma nova capacidade de execução remota. O link `#rpz-manual-sync` continua abrindo a seção.
- **Histórico recente**: no máximo 5 eventos. **Ver histórico completo** abre os mesmos dados já carregados, limitados pelo backend a até 80 eventos dos últimos 30 dias. Não há busca adicional nem arquivo ilimitado. O escopo por servidor, a ordem e os cortes temporais por vínculo continuam iguais.
- Eventos: ciano para sincronização, verde para adição, laranja para remoção. Contagens formatadas em português; mensagens de lista mantêm o link para o histórico da fonte. Remoções representam desativação de regras.
- Modais usam `showModal()`/`close()`, fechamento por botão e Escape. Em telas grandes, a página reserva espaço à direita enquanto o modal está aberto; em telas menores ele sobrepõe a página. Layout responsivo e variáveis de tema preservados.

## Restrição de IP — somente admin

O card resume IPs ativos, versões IPv4/IPv6 e quantidade de pendentes. A frase “Restrição desativada para token legado” foi retirada do resumo.

**Gerenciar IPs** abre um painel lateral com:

1. Título com SVG, contadores e botão ×.
2. Controle de acesso com método e ação existente de ativar/desativar restrição.
3. Campo identificado para adicionar IPv4/IPv6 ou CIDR.
4. IPs em itens compactos com versão, status, **Liberar** para pendentes e **Remover** com confirmação.
5. Orientação de dual-stack e botão **Fechar**.

O texto dentro do gerenciamento esclarece que o controle se aplica ao acesso por token e que a URL empresarial exige IP autorizado. Ocultar a frase no resumo não muda a configuração ou a ACL. O cliente não recebe esse gerenciamento.

## Listagem administrativa — `/servidores`

- Cabeçalho com **Novo endpoint** e resumo de cadastrados, ativos e inativos no painel, calculado a partir dos dados já fornecidos à view.
- Tabela com identidade/SVG do servidor e tipo DNS, empresa com link, quantidade de listas, última consulta relativa e data, estado operacional e status cadastral.
- IPs pendentes continuam sinalizados. **Ver detalhes** e **Editar** ficam visíveis; o menu secundário mantém remoção confirmada.
- Sem fontes: **Vincular listas** leva diretamente à edição.
- Ordenação existente por última consulta e paginação de 20 registros mantidas. Em telas pequenas, as linhas se apresentam como cards com rótulos dos campos.
- O caminho de renderização do cliente mantém sua listagem anterior; o novo partial é incluído apenas para admin.

## Cadastro e edição administrativos

- Breadcrumb, identificação do endpoint e atalho **Ver servidor** na edição.
- Duas colunas em desktop: Identificação/Configuração RPZ/Acesso ao feed à esquerda; Fontes habilitadas à direita. Coluna única no celular.
- Campos com cores de tema, títulos com SVGs, URL/token copiáveis e token com Mostrar/Ocultar preservados.
- Fontes com SVG de categoria, seleção destacada em verde, contador e ações Selecionar todas/Limpar seleção. A pesquisa filtra nome/descrição localmente. O filtro usa `hidden` com regra CSS específica, corrigindo o conflito com o `display: flex !important` da classe antiga.
- Barra de Cancelar/Salvar com posicionamento sticky; não há alteração no payload ou nas regras de gravação.
- Na edição admin, IPv4/IPv6 continuam informativos; IPs autorizados são gerenciados nos detalhes. Na criação, o comportamento existente de liberação dos IPs informados permanece.

## Arquivos principais

| Arquivo | Responsabilidade |
| --- | --- |
| `resources/views/servidores/show.blade.php` | Resumo, modais, seções técnicas, ações e JavaScript dos detalhes |
| `resources/views/servidores/partials/admin-detail-styles.blade.php` | Estilos dos detalhes compartilhados e gerenciamento de IPs |
| `resources/views/servidores/partials/admin-event-stream.blade.php` | Histórico recente/completo para ambos os perfis |
| `resources/views/servidores/index.blade.php` | Seleção da apresentação admin/cliente |
| `resources/views/servidores/partials/admin-index.blade.php` | Listagem administrativa |
| `resources/views/servidores/partials/admin-index-styles.blade.php` | Estilos responsivos da listagem |
| `resources/views/servidores/form.blade.php` | Formulário e filtro de fontes |
| `resources/views/servidores/partials/admin-form-styles.blade.php` | Estilos exclusivos do formulário admin |
| `resources/views/servidores/partials/form-actions.blade.php` | Ações compartilhadas do formulário |
| `resources/views/components/ui-icon.blade.php` | SVGs reutilizáveis; categorias reutilizam `list-category-icon` |

Os estilos desta etapa são inline nos partials; `public/assets/app.css` não foi alterado nesta etapa. Apesar dos nomes `admin-detail-styles` e `admin-event-stream`, esses dois partials atendem também aos detalhes do cliente.

## Validação

Regressão final: **87 testes, 503 assertions, sem falhas**, em PHP 8.4.12/PHPUnit 12.5.33, container sem rede, projeto somente para leitura e SQLite em memória:

```bash
docker run --rm --network none --read-only --tmpfs /tmp \
  -v /opt/dns-panel-rpz:/var/www/html:ro \
  --entrypoint php dns-panel-rpz-app:local \
  vendor/bin/phpunit --do-not-cache-result \
  --filter 'AdminEndpointsPresentationTest|AdminEndpointStreamTest|ClientServersPresentationTest|ServidorAllowedIpTest|ServidorIpPendenteTest|ClienteIsolamentoTest|ServidorListaAtividadeTest|MikrotikHostsEndpointTest|RpzEndpointTest|LicencaNoExpiryTest'
```

Os testes verificam apresentação admin/cliente, limite visível de 5 eventos, preservação dos 80 dados carregados, quantidade constante de queries, isolamento entre empresas e ações existentes de IP/listas/RPZ. A expectativa antiga “domínio(s)” no histórico do cliente foi atualizada para a forma exibida “domínio/domínios”, mantendo a checagem de exclusão de eventos anteriores ao vínculo.

Houve verificação temporária em Chromium/Playwright com dados fictícios: detalhes/listas/histórico, Escape, abertura das instruções de sincronização, listagem/menu de ações, cadastro/edição, busca, selecionar/limpar fontes e Mostrar/Ocultar token. Capturas em desktop e celular, com verificação de ausência de overflow horizontal. O usuário confirmou os detalhes nas visões admin e cliente. Os ajustes posteriores de Fechar e Gerenciar IPs tiveram testes HTTP/Blade; não se declara uma captura específica desses ajustes. Esta é uma seleção de regressão, não uma aprovação da suíte inteira.

## Publicação e reversão

Publicado incrementalmente em 21/09/2026, sem migrations, atualização de dependências ou alteração de dados:

| Imagem app | Conteúdo |
| --- | --- |
| `compact-detail-20260921` | Reorganização inicial dos detalhes |
| `detail-polish-20260921` | Refinamento visual, SVGs e painel lateral |
| `detail-close-20260921` | Fechar nas seções técnicas |
| `ip-drawer-20260921` | Gerenciamento de IPs atualizado |
| `client-status-20260921` | Status compartilhado com cliente |
| `admin-directory-form-20260921` | Listagem e formulário admin modernizados |

As tags acima têm o prefixo `dns-panel-rpz-app:`. A última também está em `dns-panel-rpz-app:local`. Cada imagem deriva da anterior e copia apenas os templates alterados. O serviço `app` foi recriado, `php artisan view:clear` executado e o nginx recarregado para resolver o upstream. `/login` e `/up` retornaram HTTP 200; hashes dos arquivos publicados conferidos. Não foi necessário reconstruir o nginx nesta etapa, porque não houve mudança no CSS estático.

Reversão somente da última publicação (listagem/formulário):

```bash
export RPZ_ENV_FILE=/etc/dns-panel-rpz/app.env
docker tag dns-panel-rpz-app:before-admin-directory-form-20260921 dns-panel-rpz-app:local
docker compose up -d --no-deps app
docker compose exec -T app php artisan view:clear
docker compose exec -T nginx nginx -s reload
curl --fail http://127.0.0.1:8082/up
```

Para voltar ao estado anterior a toda esta etapa, a imagem guardada é `dns-panel-rpz-app:before-compact-detail-20260921`. A reversão altera a aplicação em execução, não desfaz arquivos no Git. Só execute se houver necessidade operacional. Scripts e capturas sob `/tmp` foram auxiliares temporários; futuras publicações devem usar o código versionado e o [runbook de deploy](../RUNBOOK.md#fazer-um-deploy).
