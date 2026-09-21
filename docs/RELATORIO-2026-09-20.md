# Registro de atendimento — 20/09/2026

Escopo: diagnóstico de zona RPZ vazia no DNS-01 de um cliente, atualização manual do Unbound e ajustes de interface. Horários em America/Sao_Paulo (UTC−03). Identificação da empresa e IPs de produção omitidos deste documento versionável.

## Diagnóstico

Foram consultados os containers Docker, o código de geração da zona, os acessos do nginx e o banco SQLite em modo somente leitura. Os serviços do painel estavam em execução. O histórico comprovou que a primeira busca ocorreu antes da vinculação das listas:

| Horário | Evento observado |
| --- | --- |
| 18:36:37 | Cadastro do DNS-01. |
| 18:38:13 | Registro de consulta do DNS-01 com **0 domínios**. |
| 18:38:14 | Nginx registrou HTTP **200**, com **368 bytes** enviados ao DNS-01. |
| 18:38:40–18:39:17 | Vinculação das seis listas ao DNS-01. |
| 18:44:30 | Cadastro do DNS-02. |
| 18:45:31–18:46:02 | Vinculação das seis listas ao DNS-02. |
| 18:47:11 | Primeira consulta registrada do DNS-02 com **314.948 domínios**. |

As listas eram Ameaças Gerais, Botnets e Controle Remoto, Malware e Vírus, Mineração de Criptomoedas, Phishing e Golpes e Ransomware. Os IPs cadastrados estavam autorizados. Na consulta diagnóstica, o conjunto empresarial já continha 314.948 domínios e não havia nova solicitação do DNS-01 depois de 18:38.

**Causa confirmada:** o DNS-01 consultou o endpoint antes de haver listas vinculadas. Recebeu uma zona sem domínios das listas, ainda com cabeçalho e domínio canário. O DNS-02 consultou depois dos vínculos; o usuário confirmou que nele funcionou.

O SOA gerado informa `refresh` de 3.600 segundos. Reiniciar o Unbound pode reutilizar o arquivo de zona salvo; não deve ser tratado como garantia de download imediato. Não houve acesso direto ao sistema do DNS-01. Posteriormente, em 20/09/2026, o usuário confirmou que o DNS-01 funcionou ("dns-01 deu certo"); o incidente foi encerrado com base nessa confirmação. O painel registra a consulta antes de concluir a resposta: seu histórico não comprova, sozinho, que o Unbound carregou a zona.

## Atualização manual disponibilizada

Na tela dos servidores cujo tipo é `unbound`:

- Atalho **Antecipar sincronização** no topo.
- Bloco **Caso a sincronização não ocorra**, após as instruções de configuração e imediatamente antes de **Restrição de IP** para administradores. O bloco também fica disponível ao cliente autorizado a ver seu servidor.
- Comando `unbound-control auth_zone_transfer <nome-da-zona>` para solicitar nova busca.
- Comando `unbound-control list_auth_zones` para conferir os nomes configurados, dentro de **Se o comando apresentar erro**.
- Ambos os botões usam o rótulo **Copiar**, conforme o padrão da página.
- Orientações para executar no terminal do **servidor DNS do cliente**, como root ou usando sudo. O controle local precisa estar habilitado; uma zona com nome personalizado exige ajustar o argumento.
- Explicação de que `ok` confirma a solicitação, não a conclusão. Conferir histórico do painel e logs do Unbound.
- Tratamento de indisponibilidade/falha da API de clipboard: orientação para selecionar e copiar o texto manualmente.

O painel continua distribuindo a zona por solicitação do Unbound. Não foram adicionados agente, SSH, credenciais remotas, execução remota por botão ou alteração do intervalo de atualização. Os comandos não foram executados nos DNS do cliente neste atendimento.

## Ajustes visuais

As instruções passaram a seguir a tipografia e o espaçamento dos passos anteriores: textos de 11 px, comandos monoespaçados de 12 px e botões com espaçamento consistente.

No tema claro, o fundo escuro fixo de seis blocos se combinava com texto escuro. Os blocos corrigidos são configuração de módulos, configuração principal do cliente, validação/reinício, consulta do domínio canário, transferência da zona e consulta de zonas.

A variável `--command-background`, definida apenas no tema claro como `var(--surface-soft)`, controla esses fundos. O fallback `#080d17` preserva o fundo anterior no tema escuro. A prévia `.rpz-code` também recebeu fundo, texto e borda específicos do tema claro. As cores do tema escuro foram preservadas no resultado final.

## Arquivos e publicação

Implementação:

- `resources/views/servidores/show.blade.php`: bloco, posição, textos, botões, clipboard e fundos dos comandos.
- `public/assets/app.css`: variável do tema claro e cores da prévia RPZ.

Documentação: `README.md`, `RUNBOOK.md`, `docs/CHANGELOG.md` e este relatório.

As publicações usaram imagens derivadas das imagens locais em execução, copiando apenas os arquivos necessários. O app foi recriado e o cache Blade limpo. O nginx interno foi reiniciado ou recriado; nas alterações de CSS, o arquivo foi incluído nas imagens do app e do nginx para manter também o versionamento da URL do CSS. Os demais serviços não precisaram ser recriados para estas alterações. Não houve migration nem alteração manual dos cadastros/listas no banco.

Referências locais da publicação final: `dns-panel-rpz-app:light-fields-20260920` e `dns-panel-rpz-web:light-fields-20260920`, também apontadas pelos respectivos tags `:local`. Os tags foram reutilizados nos ajustes de texto; não são identificadores imutáveis. Os diretórios de preparação ficaram em `/root/rpz-manual-sync`, `/root/rpz-sync-layout`, `/root/rpz-command-theme` e `/root/rpz-light-fields`. Os scripts são registros operacionais, não um procedimento genérico para reutilizar após alterações futuras.

O repositório já tinha alterações em telas de empresas/listas e em testes associados antes deste atendimento. Elas foram preservadas e não são atribuídas a este trabalho. Não foi criado commit nem realizado push na publicação inicial. A revisão posterior para versionamento inclui também os ajustes já existentes nas telas de empresas/listas e seus testes.

## Validação realizada

- Teste inicial da alteração em container temporário, sem rede, fonte montada somente para leitura e banco de teste em memória: **16 testes e 144 assertions passaram**. O PHPUnit informou apenas que não conseguiu escrever seu cache de resultados no mount somente leitura.
- Renderização da tela real do DNS-01 confirmou a presença dos comandos. Após o ajuste de posição, foi validada a ordem configuração → instruções de sincronização → restrição de IP.
- Após o primeiro ajuste de tema, a renderização confirmou o uso de variável de fundo nos comandos.
- As verificações HTTP após as publicações retornaram **200** em `/login`.
- O CSS servido pelo nginx foi conferido quanto à variável de comandos e à regra de tema claro para `.rpz-code`.
- App e nginx foram observados saudáveis após a publicação inicial. Não foi realizada inspeção visual automatizada em navegador nem teste direto da sincronização no cliente remoto; o funcionamento do DNS-01 foi confirmado posteriormente pelo usuário. Os ajustes posteriores de texto/CSS não motivaram nova suíte completa.

## Revisão posterior e encerramento

O usuário confirmou o funcionamento do DNS-01. Na revisão para versionamento, duas expectativas antigas de testes ainda exigiam listas próprias na tela da empresa do cliente; foram corrigidas para conferir sua ausência, mantendo a cobertura de isolamento. A orientação da tela do servidor passou a dizer que o histórico confirma a consulta, não a entrega concluída.

Validação da revisão em container temporário sem rede, com fonte somente para leitura e SQLite em memória: **30 testes e 231 assertions passaram**, cobrindo apresentação da empresa, isolamento das listas e telas de servidores de clientes e administradores.

## Referência técnica

O comando `auth_zone_transfer` solicita a busca/transferência da zona; `auth_zone_reload` relê o arquivo local e não substitui essa operação. O suporte a `auth_zone_transfer` foi conferido também no código da versão 1.13.1, identificada no User-Agent do cliente.

- [Manual oficial de unbound-control](https://unbound.docs.nlnetlabs.nl/en/latest/manpages/unbound-control.html)
- [Implementação na versão 1.13.1](https://github.com/NLnetLabs/unbound/blob/release-1.13.1/daemon/remote.c)
