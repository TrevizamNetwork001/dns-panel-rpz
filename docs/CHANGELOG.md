# Changelog

Histórico das mudanças por data, agrupadas por área. Os commits ficam no branch `feat/dockerize-rpz` (PR nº 1 para o `master`). Este arquivo começa em 17/09/2026; o que veio antes está no histórico do git.

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
