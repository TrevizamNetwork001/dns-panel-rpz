# Changelog

Histórico das mudanças por data, agrupadas por área. Os commits ficam no branch `feat/dockerize-rpz` (PR nº 1 para o `master`). Este arquivo começa em 17/09/2026; o que veio antes está no histórico do git.

## 26/10/2026

### Origem de download RPZ separada: feeds

- Ativado `https://feeds.trevizamnetwork.com.br` em IPv4/IPv6, com certificado válido para feeds e painel e renovação preservada. Gateway feeds expõe somente os arquivos RPZ, mantendo licença e ACL por IP.
- Painel passa a gerar URLs pelo `RPZ_FEED_BASE_URL`; nome das zonas, seriais, arquivos existentes e URLs antigas preservados. Imagem `rpz-feeds-20261026` aplicada apenas ao app.
- 34 testes PHP / 195 asserções e três testes de bootstrap com Unbound real aprovados. HTTPS público confirmado nas duas famílias; teste externo sem ACL foi negado. Primeira busca em cliente após trocar a URL ainda aguarda evidência. [Procedimento, testes e rollback](contabo/rpz-feeds-2026-10-26.txt).


### Investigação da primeira busca RPZ

- Reproduzida em laboratório falha de primeira busca quando o hostname da URL coincide com o nome da zona; a integração atual usa essa combinação. Com hostname distinto, download automático ocorreu em cerca de 0,061 s. Nenhuma mudança ativa aplicada; logs do cliente ainda solicitados. [Evidências e correção recomendada](contabo/rpz-primeira-busca-2026-10-26.txt).


### Diagnóstico RPZ compatível com Unbound

- Encerramento validado pelo usuário: DNS da União (empresa 13, servidor 8) recebeu `company:13` revisão 58 às 12:01:21 e informou serial 58 carregado às 12:01:23; 93.139 domínios, canário A/AAAA com NXDOMAIN e contador RPZ de 78.425 ações. Revisão 59 do histórico pertence à zona individual; não indica atraso na empresarial. Evidência e limites registrados no relatório abaixo.

- Adicionado `php artisan rpz:verify [zone_key]`: auditoria somente de leitura de versão, arquivo publicado, SHA-256, tamanho, regras, canário e limites DNS, com códigos de saída para automação.
- Telas do servidor Unbound e da empresa oferecem comandos para conferir serial local, testar A/AAAA e consultar contadores sem zerá-los; orientações para logs temporários e interpretação das evidências.
- 18 testes PHP / 67 asserções e integração isolada com Unbound real aprovados para NXDOMAIN, redirecionamento, exceções, logs e contadores. Imagem `rpz-diagnostics-20261026` aplicada somente ao serviço app. [Detalhes, testes e rollback](contabo/rpz-diagnosticos-unbound-2026-10-26.txt).


### Auditoria da fonte legada ANATEL

- Comparados 40.749 nomes do feed com a lista ANATEL 14 e os artefatos RPZ publicados: 40 excluídos por regras ativas, 40.709 elegíveis já ativos no banco e 40.705 presentes em cada RPZ verificado que assina ANATEL, exceto DNS-DEMO (teste).
- Quatro nomes de 253 caracteres excedem o limite DNS ao receber o sufixo RPZ (280 caracteres). Zona de controle aprovada e zona com as quatro entradas rejeitada por named-checkzone em laboratório isolado. Elevar a constante não produz RPZ válido.
- Fonte mantida ativa enquanto se decide a desativação com essas exceções; nenhum domínio, configuração ativa ou código de produção alterado. Relatório, evidências e procedimento: [auditoria ANATEL](contabo/anatel-feed-legado-2026-10-26.txt).

## 23/10/2026

Estado final e roteiro de retomada: [Unbound/RPZ e DNS-03](contabo/retomada-2026-10-23-unbound-rpz.txt).
As etapas abaixo são históricas; a resposta SOA externa IPv4/IPv6 e a busca
periódica da revisão 88 foram comprovadas depois das pendências intermediárias.
NOTIFY continua sem ACK. Alterações locais ainda sem commit/push.

### Validação do ciclo automático do DNS-03

- Após desativação do domínio de teste, artefato empresarial publicado com serial/revisão 88 e sem `teste-rpz.example.com`.
- Painel confirmou requisição do DNS-03 às 19:41:03 de 23/10/2026 (Brasília), company:11, revisão 88, Unbound 1.26.2, no ciclo esperado após a sondagem de 18:41. Sem nova intervenção manual informada pelo cliente.
- Confirma a busca automática da revisão nova; instalação no cliente depende da evidência local. NOTIFY permanece sem ACK e não é validado por esse resultado.

### DNS-03 com URL empresarial — correspondência SOA e NOTIFY

- Adicionado mapeamento explícito `RPZ_PRIMARY_COMPANY_SERVER_IDS=15`: sondagens dos IPs autorizados do DNS-03 selecionam `company:11`, correspondente à URL empresarial configurada pelo cliente. Sem fallback para serial individual se o artefato empresarial estiver ausente.
- NOTIFY empresarial envia somente aos servidores mapeados da mesma empresa e incluídos no piloto; a publicação individual deixa de enviar avisos ao cliente mapeado. Demais clientes preservam o comportamento anterior.
- Publicado `dns-panel-rpz-app:rpz-company-primary-20261023` nos serviços recuperados; overlay em `docker/Dockerfile.rpz-company-primary-overlay`. Listeners root precisam de DAC_OVERRIDE além de NET_BIND_SERVICE para acessar os volumes restritos de www-data; painel e workers seguem com as capabilities anteriores.
- 13 testes / 37 asserções aprovados em banco descartável. Validação no runtime: SOA empresarial serial 86 corresponde ao artefato publicado para IPv4 e IPv6 autorizados; login HTTPS 200. Teste NOTIFY empresarial: `unconfirmed`, três tentativas, sem ACK. Isso não comprova bloqueio nem instalação no cliente.
- Pendente evidência externa do cliente: dig via IPv4/IPv6 e serial instalado. Arquivo histórico mostrado pelo cliente tinha serial 114; conferir o serial atual antes de concluir a recuperação automática após restauração do banco.

### Primário RPZ IPv4/IPv6 com nome estável

- Publicados listeners separados em `213.199.47.105:53/udp` e `[2a02:c207:2368:3678::1]:53/udp`, imagem `dns-panel-rpz-app:rpz-dualstack-20261023`. Rede do host preserva IP de origem, inclusive IPv6, para a ACL; bind restrito aos IPs públicos, sem interferir no systemd-resolved local. Contêineres com filesystem somente leitura, capabilities limitadas a NET_BIND_SERVICE e DAC_OVERRIDE para os volumes restritos.
- `RpzPrimaryServe` seleciona AF_INET/AF_INET6 conforme o bind, restringe o socket IPv6 a IPv6 e encerra sem esperar novos pacotes ao receber SIGTERM/SIGINT. Receita incremental versionada em `docker/Dockerfile.rpz-primary-overlay`.
- Nome escolhido: `primary-rpz.trevizamnetwork.com.br`; A `213.199.47.105`, AAAA `2a02:c207:2368:3678::1`, DNS sem proxy. Ambos conferidos no resolvedor público, incluindo consulta de saída via IPv6. Cliente usa esse hostname em `primary:` e mantém a URL HTTPS. Em migrações, atualizar A/AAAA, manter o antigo serviço durante a transição/TTL e validar a permissão de NOTIFY no destino.
- Validação isolada: 17 testes PHPUnit / 61 asserções (responder/ACL IPv6, NOTIFY e segurança Unbound); 2 testes reais de socket UDP IPv4/IPv6 aprovados, incluindo origem e SIGTERM; listeners de produção ativos e login HTTPS 200. A resposta SOA pela rede externa ainda depende do teste no DNS-03 autorizado. NOTIFY permanece restrito ao servidor 15 e enviado por IPv4; não foi afirmada validação externa de NOTIFY IPv6.

### Restauração do primário RPZ para o DNS-03

- Reativado `rpz-primary` no compose recuperado: `213.199.47.105:53/udp` encaminha ao listener 5353. Usa os volumes e imagem atuais; não executa init ou migrations.
- NOTIFY reativado apenas para servidor 15 (DNS-03 / Speed Fiber), por IPv4, reproduzindo o escopo do piloto anterior. Cliente precisa substituir o primary antigo e revisar a permissão do novo IP para NOTIFY; nenhum acesso remoto ao cliente foi feito.
- Validação: listener iniciado; resposta SOA para o IP autorizado anuncia serial publicado 103, refresh 3600 e retry 600; IP de documentação não autorizado rejeitado pelo responder; login HTTPS 200 e aplicação saudável. Falta confirmar consulta UDP desde o cliente e ACK do NOTIFY; funcionamento externo ainda não declarado validado.
- Cliente havia mostrado serial 114 em arquivo histórico; houve nova busca HTTPS às 16:37:16 (Brasília), revision 86. Conferir serial efetivamente instalado antes de concluir a recuperação automática; revision do painel não é o serial SOA. União usa zona empresarial e continua fora deste piloto.

### Segurança do Unbound — referência 1.26.2

- Página `/unbound-seguranca` revisada com as cinco CVEs corrigidas em 21/10/2026: CVE-2026-97206, CVE-2026-50106, CVE-2026-96405, CVE-2026-109125 e CVE-2026-35501. Inclui gravidade, condições de exposição e ligação ao anúncio oficial.
- Avisos de versão passam a usar 1.26.2 como referência upstream; 1.26.1 passa a exigir revisão. A detecção continua pelo User-Agent da busca RPZ, sem acesso remoto ao DNS.
- Corrigida a associação indevida entre versão antiga e interrupção de sincronização. O texto esclarece backports da distribuição e não trata o selo como confirmação de vulnerabilidade.
- Corrigida a contagem descritiva da 1.26.1: a CVE-2026-33278 foi corrigida na 1.25.1. Orientação de atualização passa a exigir conferência do pacote disponível e da versão em execução.
- Fonte: https://nlnetlabs.nl/projects/unbound/security-advisories/ e https://community.nlnetlabs.nl/t/unbound-1-26-2-released/3495 .
- Validação local: sintaxe PHP, compilação Blade e verificação direta dos limites de versão aprovadas em contêiner isolado, sem rede ou volumes de produção. Na primeira validação, PHPUnit não estava disponível na imagem. Mais tarde as dependências locais foram encontradas e os testes de versão/página passaram na seleção de 17 testes / 61 asserções registrada acima. Publicado em `compose.staging.yml` com a imagem `dns-panel-rpz-app:unbound-security-20261023`, derivada da imagem PHP 8.4.27 anterior e contendo apenas o model e as três views alteradas. Imagem anterior preservada. Aplicação e nginx saudáveis; serviços auxiliares ativos; login HTTPS 200 e página autenticada HTTP 200 com as cinco CVEs conferidas. Sem migrations.

## 02/10/2026

### Backups SQLite e Cloudflare R2

- Backup automático semanal em `.bak.gz`, com snapshot SQLite, compressão verificada e publicação do arquivo por renomeação. Temporários são removidos ao encerrar o script; retenção local de 14 dias.
- O serviço verifica a cada hora se decorreram sete dias desde o último sucesso. Execução manual permanece disponível e reinicia esse prazo. Trava compartilhada impede execuções simultâneas; o loop continua após falhas.
- Após envio confirmado ao R2, dez ou mais backups são reduzidos aos cinco mais recentes. A retenção por idade também preserva cinco. Os `.bak` antigos e `.bak.gz` novos participam da regra, sem recompressão retroativa.
- Consulta administrativa do espaço via API, com quantidade de objetos, tamanho total do bucket, horário da consulta e margem estimada até 10 GB. Aviso na tela a partir de 8 GB; a rotina de backup também registra o alerta na auditoria. A estimativa não inclui outros buckets nem calcula cobrança em GB-mês.
- Teste de comunicação confirma escrita, leitura e exclusão antes de informar sucesso. Remove o objeto temporário exclusivo e o arquivo fixo `backups/teste-conexao.txt` deixado pela versão anterior. Erros de execução manual passam a ser exibidos como falhas.
- Publicação nos serviços `app` e `backup`, com limpeza de caches de rotas/views e reinício do nginx. Sem migrações. Imagem anterior preservada para reversão; detalhes operacionais e restauração gzip no RUNBOOK.
- Limpeza em produção autorizada: 32 arquivos / 2.563.538.944 bytes antes do novo backup; novo gzip de 25.691.513 bytes; remoção de 28 backups antigos; cinco arquivos / 409.257.337 bytes ao final.
- Validação: **19 testes / 45 asserções**; compressão e restauração de SQLite; comunicação real com R2 sem resíduo de teste; SHA-256 remoto igual ao local; integridade do backup real retornou `ok`; tela renderizada e `/login` com HTTP 200.

## 30/09/2026

### Histórico de servidores

- Padronizados os nomes exibidos no histórico: **Zona consultada**, **Domínios adicionados**, **Domínios removidos**, **Zona atualizada**, **Servidor notificado** e **Notificação falhou**.
- As descrições acompanham os novos nomes; o evento de consulta não repete o nome do servidor na linha seguinte.
- “Sincronização concluída” não é usado para avisos: fica reservado a uma confirmação real de recebimento e aplicação da nova versão pelo servidor.
- Alteração limitada à apresentação do partial Blade do histórico. Tipos internos, banco, auditoria, filtros, ordenação, dados antigos, ícones, cores e comportamento responsivo foram preservados.
- Formulários administrativos de licença, empresa, fonte e usuário receberam o mesmo padrão visual responsivo dos endpoints. A configuração RPZ deixou de aparecer no formulário de empresa, pois pertence aos servidores.
- O formulário de Licenças foi refinado para um painel único contínuo, com Empresa, Validade e Capacidade/status como seções internas, divisores discretos, ações integradas e layout responsivo alinhado à referência visual.
- Licenças não podem trocar de empresa durante a edição: a empresa fica somente leitura na interface e `empresa_id` é removido do payload de atualização no backend. O cadastro de servidor agora aplica o limite de licenças ativas e válidas também para administradores.
- Validação específica: **20 testes / 63 assertions** para empresas, licenças e usuários; testes de limite cliente/administrador aprovados. Publicação incremental do app concluída, views limpas/recompiladas, nginx recarregado e `/login` respondeu HTTP 200.
- Listagens administrativas de Empresas, Licenças, Usuários e Sugestões seguem o mesmo padrão compacto, com tabela responsiva no desktop e cartões no mobile; filtros e ações existentes foram preservados.
- O filtro visual de validade foi removido da listagem de Licenças, mantendo a consulta e os demais dados sem alteração.

## 21/09/2026

### Acesso ao gerenciamento de ACL

- Adicionado **Gerenciar ACL** na tabela de servidores da empresa. O link abre diretamente o painel de IPs do servidor, via `#endpoint-ips`, para adicionar ou liberar regras. Permissões administrativas e regras de autorização existentes preservadas.

### Correção da apresentação e revisão da suíte

- **Minha empresa** passa a exibir Nome fantasia e Telefone salvos no cadastro; antes, os dois campos mostravam apenas um traço fixo. Campos vazios continuam exibindo `—`.
- Corrigidas expectativas antigas de apresentação: a empresa mostra resumo de utilização dos servidores e botão de edição desabilitado para cliente; nomes individuais ficam na tela de servidores. Descrições das listas são conferidas no catálogo e nas listas vinculadas ao servidor, conforme o layout vigente. Verificações de isolamento entre empresas e bloqueio de edição permanecem nos testes.
- Suíte completa aprovada após incluir a tela administrativa: **416 testes / 2.514 assertions, sem falhas**, em SQLite em memória e contêiner sem rede. O ambiente de teste recebeu `bind-tools` para executar `named-checkzone`; a ausência desse binário na imagem de produção era a causa da falha do teste de sintaxe DNS. Nenhum teste foi desabilitado.
- Esta revisão resolve as falhas preexistentes registradas abaixo. Não exigiu nova migration.
- Ao abrir uma empresa pelo administrador, o perfil e o resumo de licença agora seguem o layout atual. Servidores aparecem em tabela responsiva com tipo, listas, última consulta, status e ações. **Novo servidor** abre o formulário com a empresa selecionada. Fontes, usuários e controles RPZ continuam disponíveis; distribuição RPZ fica recolhível e abre automaticamente após testar ACL. Detalhes em [EMPRESAS.md](EMPRESAS.md).

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
