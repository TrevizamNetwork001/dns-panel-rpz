# Registro de atendimento — 21/09/2026

## Escopo

Foi concluída a reorganização visual das telas de servidores e do dashboard do cliente, usando a referência visual disponível em `referencias/`. O trabalho de dashboard foi mantido restrito à apresentação e ao reaproveitamento de dados existentes.

## Telas de servidores

- Detalhes do endpoint ficaram mais compactos, com nome, empresa, status, última consulta, tipo DNS e modo de bloqueio no topo.
- Os botões **Sincronizar agora** e **Editar** foram preservados.
- A tabela extensa de fontes vinculadas foi substituída por um resumo com quantidade, tags de exemplo e **Ver listas**.
- As listas vinculadas passaram a abrir em modal lateral, com nome, tipo e ação de remoção.
- A seção de fontes disponíveis para adição foi retirada da visualização; a seleção continua na edição do servidor.
- O acesso RPZ deixou de ter um card separado quando a URL já aparece na configuração técnica.
- Restrição de IP foi reduzida a um card operacional com gerenciamento lateral; a frase sobre restrição desativada para token legado foi removida.
- Configuração do Unbound e solução de problemas ficaram recolhíveis e receberam controle de fechamento quando abertas em modal.
- O log foi reduzido aos cinco eventos mais recentes, com acesso ao histórico completo.
- A listagem administrativa de servidores foi modernizada e aproximada do padrão visual do cliente; o formulário administrativo também foi reorganizado.
- As telas de detalhe funcionam para admin e cliente, preservando as ações e os dados permitidos para cada perfil.

## Dashboard do cliente

Os quatro cards superiores foram mantidos: domínios bloqueados, listas disponíveis, servidores em uso e servidores contratados. Os ícones originais de servidores em uso e servidores contratados foram preservados.

Foi criado o bloco **Saúde da distribuição RPZ**, com:

- buscas registradas nos últimos 30 dias;
- última sincronização, com data/hora e tempo relativo;
- intervalo médio entre buscas, calculado somente quando existem dados suficientes;
- cobertura da licença com base na capacidade já registrada;
- gráfico de sincronizações por dia;
- resumo de status dos servidores.

O status é derivado da última sincronização: servidor em dia quando a consulta recente ocorreu, atenção quando está há dois dias ou mais sem consulta e sem sincronização quando nunca houve consulta. Não foram criadas métricas de consultas DNS, bloqueios de rede ou ameaças interceptadas.

Os SVGs dos indicadores foram alinhados verticalmente dentro dos cards. Uma tentativa de adicionar um SVG ao título do gráfico foi revertida a pedido do usuário; o título voltou ao estado anterior.

## Dados e regras preservados

- Não foram alteradas regras de backend, ACL, licença, sincronização, geração RPZ, rotas ou banco.
- Não foi criada execução remota de comandos no DNS do cliente.
- O dashboard usa `ServerSyncLog`, `last_synced_at`, servidores da empresa e capacidade de licença já existentes.
- A referência visual permaneceu fora do versionamento da aplicação.

## Arquivos do dashboard

- `app/Http/Controllers/DashboardController.php`: cálculo das métricas reais do período, última sincronização, intervalo, cobertura e status.
- `resources/views/dashboard/cliente.blade.php`: composição do bloco de saúde e status por servidor.
- `resources/views/dashboard/partials/grafico-atualizacoes.blade.php`: título e descrição do gráfico como sincronizações por dia.
- `resources/views/dashboard/partials/cliente-dashboard-styles.blade.php`: estilos responsivos e alinhamento dos SVGs.
- `tests/Feature/ClientAreaPresentationTest.php` e `tests/Feature/ClienteDashboardGraficoTest.php`: expectativas atualizadas para os rótulos apresentados.

## Validação e publicação

- Suíte direcionada: **11 testes e 61 assertions aprovados**.
- `git diff --check`: aprovado.
- Imagem Docker do app reconstruída sem rede; cache Blade limpo.
- Nginx recarregado após a publicação.
- Health check de `/up`: **HTTP 200**; container do app saudável.
- As alterações do dashboard permanecem sem commit, conforme solicitado.

