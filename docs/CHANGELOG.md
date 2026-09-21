# Changelog

Histórico das mudanças por data, agrupadas por área. Os commits ficam no branch `feat/dockerize-rpz` (PR nº 1 para o `master`). Este arquivo começa em 17/09/2026; o que veio antes está no histórico do git.

## 21/09/2026

### Cadastro e edição de empresas

- Adicionados campos opcionais **Nome fantasia** (até 255 caracteres) e **Telefone** (até 32 caracteres) no formulário administrativo. Telefone aceita formatação, código de país e ramal; usa input `tel` sem máscara obrigatória.
- Valores persistidos no model e validados no controller, com preenchimento na edição e possibilidade de limpar os campos. Empresas existentes continuam válidas com valores nulos.
- Migration `2026_09_21_180000_add_contact_fields_to_empresas_table.php` adiciona duas colunas nullable. Fazer backup antes de aplicá-la; reverter essa migration remove os dados desses campos.
- Testes específicos: **2 testes / 20 assertions aprovados**, cobrindo criação, edição, limpeza, campos opcionais e limites. Duas falhas de `ClientCompanyPresentationTest` foram reproduzidas com model, controller e formulário originais; são preexistentes.
- Publicado com a imagem `dns-panel-rpz-app:company-contact-20260921`: backup local `database.sqlite.auto-20260921-195802.bak` e envio ao R2 concluídos antes da migration. Serviço app recriado, Blade recompilado e `/login` com HTTP 200. Imagem anterior preservada em `dns-panel-rpz-app:before-company-contact-20260921`.

### Listas do cliente

- Nova apresentação baseada na imagem de `referencias/`: cartões responsivos com ícones por categoria, nome, descrição, selo Catálogo/Própria e quantidade de domínios ativos.
- Ordenação por nome antes da paginação de 20 itens; desempate por ID decrescente. Ordenação alfabética administrativa preservada.
- Sem campo de busca. Cartões apenas informativos: sem link, navegação ao clicar ou efeito ao passar o mouse, conforme ajuste solicitado após a primeira publicação.
- Escopo das listas preservado: catálogo e listas da própria empresa. Sem migrations ou mudanças nas regras de distribuição RPZ.
- Antes do ajuste final dos cartões: **13 testes / 79 assertions aprovados**. Após o ajuste: `git diff --check`, compilação Blade e `/login` com **HTTP 200**.
- Publicado no serviço Docker `app`. Implementação, limites das validações e manutenção em [LISTAS-CLIENTE.md](LISTAS-CLIENTE.md).

### Detalhes de endpoints e administração
- Detalhes compactos para admin e cliente com status em faixa única e SVGs, resumo de listas, modal lateral de listas e histórico recente limitado a 5 eventos. Histórico completo preserva os até 80 eventos dos últimos 30 dias já carregados.
- Configuração técnica e Solução de problemas recolhíveis, com botão Fechar no rodapé. Sincronizar agora abre as instruções existentes do Unbound, sem execução remota. URL incorporada à configuração; adição de fontes permanece na edição.
- Restrição de IP em resumo compacto e gerenciamento lateral com SVGs, adicionar, liberar pendentes, remover e fechar. Retirada a frase sobre restrição desativada para token legado do resumo; regras de ACL preservadas.
- Listagem do admin modernizada com contadores, ícones, status, atalhos de detalhes/edição e apresentação responsiva. Depois, simplificada para o mesmo padrão enxuto da lista do cliente: nome/empresa, tipo, listas, última consulta, status e ações, sem cards e colunas extras. Cadastro/edição admin em duas colunas, fontes com ícones e seleção destacada, barra de ações sticky e correção da ocultação dos resultados na busca local.
- Sem alterações de backend, rotas, banco, sincronização, geração RPZ ou permissões. Referência visual local excluída do versionamento.
- Regressão final: **87 testes / 503 assertions aprovados**. Verificação Chromium/Playwright com dados fictícios em desktop e celular. Publicações Docker concluídas, cache limpo, HTTP 200 e hashes conferidos. Manutenção, limites e reversão em [INTERFACE-ENDPOINTS.md](INTERFACE-ENDPOINTS.md).

### Dashboard do cliente
- Dashboard reorganizado conforme a referência visual, mantendo os quatro cards de resumo existentes e os SVGs de servidores em uso e contratados.
- Novo bloco **Saúde da distribuição RPZ** com buscas dos últimos 30 dias, última sincronização, intervalo médio, cobertura da licença, gráfico de sincronizações por dia e status dos servidores.
- Métricas calculadas apenas a partir de `ServerSyncLog`, `last_synced_at`, servidores da empresa e capacidade da licença; não foram inventadas métricas de bloqueio, ameaças ou consultas DNS de rede.
- Status derivado da última sincronização, com estados **Em dia**, **Atenção** e **Sem sincronização**.
- SVGs dos indicadores centralizados nos cards; ajuste posterior que adicionaria um SVG ao título do gráfico foi revertido para preservar o layout anterior.
- Validação direcionada: **11 testes / 61 assertions**, `git diff --check` e health check HTTP 200 após publicação Docker.
- Detalhamento completo em [RELATORIO-2026-09-21.md](RELATORIO-2026-09-21.md).

### Servidores do cliente
- Tabela com Nome, Tipo, Listas, Última sincronização, Status e Ações, sem IPs públicos. Botão Editar visível e menu de ações secundárias preservado; estado de sincronização normal apresentado como **Em dia**.
- Cadastro e edição organizados em Identificação, Configuração DNS e Listas habilitadas. Campos compactos, aviso NXDOMAIN discreto e rodapé dentro do card, com exclusão confirmada, Cancelar e Salvar alterações/Cadastrar servidor.
- Seletor compartilhado com pesquisa por nome/descrição (sem distinção de acentos), chips removíveis, contador e catálogo com Adicionar/Adicionada. Seleção preservada após erro de validação, inclusive quando todas as listas foram removidas.
- Ícones SVG locais com traço uniforme; cores por categoria no catálogo e nos chips. Adicionar em azul-ciano, Adicionada em verde; status com espaço de 6 px entre indicador e texto. Tema claro preservado.
- Sem mudanças em controllers, API, banco, licença, validações de IP, ACL, sincronização ou permissões.
- Validação final: **86 testes / 468 assertions**, além de verificação isolada do JavaScript do seletor durante a implementação. Uma falha preexistente no teste de descrições do dashboard foi reproduzida com as views originais e ficou fora desta seleção de regressão.
- Publicado nos contêineres app/nginx; cache de views renovado, HTTP 200 e hashes do CSS servido conferidos. Detalhes de manutenção e arquivos em [SERVIDORES-CLIENTE.md](SERVIDORES-CLIENTE.md).

## 20/09/2026

### Diagnóstico e sincronização RPZ
- Diagnóstico de DNS-01 que recebeu zona com zero domínios antes da vinculação das listas. O DNS-02 consultou depois e recebeu 314.948 domínios. Linha do tempo e limites da verificação no [registro do atendimento](RELATORIO-2026-09-20.md).
- Atalho **Antecipar sincronização** e bloco **Caso a sincronização não ocorra** na tela Unbound, após a configuração e antes da restrição de IP. Comandos `auth_zone_transfer` e `list_auth_zones`, ambos com botão **Copiar**, para executar no DNS do cliente. Não há execução remota pelo painel.
- Orientações sobre controle local habilitado, nome da zona, diferença entre solicitação e carregamento e reutilização do arquivo salvo após reinício. Tratamento de falha/indisponibilidade do clipboard.

### Interface e tema claro
- Painel de listas próprias da empresa oculto para clientes; administradores mantêm o painel Fontes. Painel de servidores vinculados à lista com largura compacta para clientes. Testes de apresentação ajustados ao comportamento.
- Tipografia e espaçamento do novo bloco alinhados às instruções existentes.
- Corrigido contraste nos seis blocos de comandos/configuração e na prévia RPZ no tema claro; cores anteriores preservadas no tema escuro.
- Publicação Docker concluída. Validação inicial: 16 testes/144 assertions; renderização da tela, posição das instruções, HTTP 200 e CSS servido conferidos. Sem migrations; funcionamento do DNS-01 confirmado posteriormente pelo usuário, sem verificação remota direta.

### Revisão de segurança e dados RPZ
- Auditoria dos domínios indicados: `jhkkjkj.com`, `weerrhoop.cc` e `ojsbdjn.com` foram desativados na lista **ANATEL — Bloqueios Oficiais**, receberam exclusões exatas para não voltarem em importações e ficaram classificados para monitoramento.
- Criada a lista manual global **Segurança — Malware / C2 / IoT** (ID 19), com `3gipcam.com`, `cross-compiling.org`, `i-kiss-boys.com`, `furry-femboys.top`, `twinkfinder.nl` e `wpad.domain.name`. A lista foi vinculada aos sete servidores ativos existentes que já usavam listas de segurança.
- `wpad.domain.name` foi retirado do conjunto ativo da ANATEL. O gerador RPZ bloqueia somente esse FQDN; não são cadastrados `wpad`, `domain.name` nem curingas de WPAD.
- As justificativas, fontes e limites da evidência estão em [SEGURANCA-2026-09-20.md](SEGURANCA-2026-09-20.md). Não houve migration; foram alterações de dados operacionais registradas na auditoria.

### Administração e auditoria
- A listagem administrativa de servidores passou a ordenar **Última consulta** da mais recente para a mais antiga; servidores sem consulta ficam no final.
- A tela de auditoria passou a usar nomes claros em português, categoria **RPZ**, identificação separada de quem executou, empresa e servidor/lista envolvidos, IP explícito e detalhes longos sob **Ver detalhes**. Eventos sem autoria registrada aparecem como **Sem usuário identificado**.
- A tela **Minha empresa** do cliente não exibe o painel de listas próprias enquanto essa função não estiver disponível para o cliente; administradores continuam vendo **Fontes**.

### Publicação e validação adicional
- Commits locais de hoje: `b868262`, `98c7b96`, `88ae93e` e `17e1135`.
- A lista de segurança foi publicada no container `dns-panel-rpz-app:security-20260920`; a ordenação no `:server-order-20260920`; os textos da auditoria no `:audit-texts-20260920`; e a tela de empresa no `:company-view-20260920`. App e nginx ficaram saudáveis e `/login` retornou HTTP 200 após as publicações.
- Validação: 45 testes/121 assertions para RPZ e WPAD, zona de teste aprovada pelo `named-checkzone`; 16 testes/289 assertions para auditoria e dashboard; 16 testes/144 assertions para a tela de servidores. A sincronização nos DNS remotos ocorre no refresh normal de 1 hora e não foi forçada remotamente.

## 18/09/2026

### Servidores e liberação de IP
- **Cliente informa o IP do DNS, admin libera** (`1827284`): ao cadastrar o servidor o cliente informa o IP de saída (IPv4 ou IPv6, obrigatório para cliente). Entra como `pending` em `server_allowed_ips` e **não dá acesso** à zona; a ACL só considera IPs `active`. Aviso no Telegram, selo "IP aguardando liberação" na lista de endpoints e botão **Liberar** na tela do servidor (admin). Detalhes no README, "Liberação de IP".
- **Admin libera no cadastro** (`8be16e8`): admin que cadastra o servidor com IPv4/IPv6 já cria o IP como liberado (só na criação; na edição os campos são informativos).
- **Passos do Unbound na tela do servidor** (`285c318`, `8be16e8`, `d51fbd9`): passo 1 (`module-config: "respip validator iterator"`), passo 2 (bloco `rpz:`), passo 3 (`unbound-checkconf` e só então `systemctl restart unbound`), passo 4 (`dig` no domínio canário, esperando NXDOMAIN), legenda dos campos e caixa "Pontos de atenção". O `rpz-log` sai **comentado** por padrão.
- **Limpeza de servidores inativos** (`4966d44`): rotina diária (03:30) que **avisa** (Telegram + Auditoria + banner na tela do servidor) e só depois do prazo de aviso **remove** o servidor que parou de consultar a zona. **Desligada por padrão**; configurável em Configurações → Geral (padrão 60 dias sem consulta, 7 dias de aviso). `--dry-run` simula sem alterar nada.

### Dashboard e telas do cliente
- **Gráfico "Atualizações de servidores"** (`e7410d0`): consultas à zona por dia, últimos 30 dias, dos servidores da empresa. SVG desenhado no servidor (sem JS/CDN).
- **Ajustes de layout** (`e20748d`, `a52404e`, `bcff107`, `48a9a0a`): espaço entre cartões e gráfico, painel "Minhas sugestões de domínio" removido (já existe no menu), dados da conta no perfil sem corte, painéis "Listas disponíveis" e "Meus servidores" alinhados no topo e com altura pelo conteúdo.
- **Descrição das listas** (`1400f8a`): frase curta de "para que serve" sob o nome, nas telas do cliente.

### Privacidade e isolamento entre clientes
- **Vazamento corrigido** (`c5d12d3`): a tela de uma lista de catálogo mostrava os servidores e as empresas de **todos** os clientes. O filtro agora é feito na consulta.
- **Cliente não vê como as listas são alimentadas** (`c4ab91e`, `a8eef75`, `ea991a2`): saíram o painel "Sincronização automática" (incluindo a **URL do feed de origem**), o selo "fonte externa", o rótulo "Externa", o painel "ANATEL / PDF" e o botão Histórico/Exclusões da ANATEL da tela da lista.
- **Token fora da página do cliente** (`6552c7d`): o bloco "Acesso ao feed" saiu do formulário do cliente e o JS deixou de embutir o token real.
- **Testes de isolamento** (`eacea01`): `ClienteIsolamentoTest` cobre as rotas que o cliente alcança (IDOR, `empresa_id`/`lista_ids` forjados, busca de domínios, sugestões, rotas de admin).
- **Repositório passou a privado** e IPs/nomes reais de testes e docs foram trocados por valores de documentação (`640557f`).

### Página de bloqueio
- **Não implementada, por decisão** (`0586b73`): o estado real, os riscos (conexão fechada pelo nginx compartilhado, alerta de certificado em HTTPS, carga) e o plano para quando houver IP/VPS dedicado estão no README. O formulário do servidor avisa que a opção não está disponível.

## 17/09/2026

### ANATEL
- **Cadastro manual direto na lista ANATEL** (`8b05dad`, `2843c17`): a importação de PDF só adiciona/reativa, nunca apaga, então o admin pode cadastrar à mão (ex.: PDF em formato imagem). Listas "externas" continuam bloqueadas para edição manual (o sync as sobrescreve).
- **Exclusões com abas Exato/Regex e edição** (`a762a93`, `8404608`): a edição reprocessa a lista (reativa o que não bate mais, desativa o que passou a bater).

### Auditoria
- **Reformulada** (`a3b6bce`): rótulos em português para todas as ações, filtros de categoria/usuário/período no banco, descrição completa e paginação.

### Interface
- **Paginação** (`fef411c`): 13 telas usavam a paginação padrão do Laravel (sem estilo, setas gigantes); trocadas por `pagination.compact`.

## Migrations novas
- `2026_09_18_000001_add_inatividade_avisada_em_to_servidores_table` — coluna `servidores.inatividade_avisada_em`.
- `2026_09_18_000002_add_servidor_created_index_to_server_sync_logs` — índice `(servidor_id, created_at)` em `server_sync_logs`.

O `docker/init.sh` **não** roda `migrate`: depois de subir uma versão com migration nova, rode `docker compose exec app php artisan migrate --force` (com backup antes; ver RUNBOOK, "Fazer um deploy").

## Alterações de dados em produção (fora do git)
- As 7 listas do catálogo foram renomeadas e ganharam descrição só com o propósito (sem nome de fonte). Os nomes antigos ficaram na Auditoria (`lista.updated`).
- A empresa **CONECTA NETWORK** (contrato cancelado) foi removida, com o servidor e a licença dela.
- A lista "ANATEL (Manual)" foi removida pelo admin; a lista ANATEL passou a aceitar cadastro manual.
- Repositório GitHub tornado **privado** (estava público, apesar do README).
