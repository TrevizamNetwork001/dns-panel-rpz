# Diretrizes visuais e proposta de login do RPZ

As próximas melhorias devem manter o painel compacto, legível e discreto. A tela de login pode receber uma identidade visual mais marcante, preservando a autenticação existente. Este documento registra as referências avaliadas e a direção para um trabalho futuro no login; ele não representa uma implementação dessa tela.

## Preferências definidas para o painel

- Botões e seletores proporcionais ao texto; ícones pequenos e consistentes.
- Evitar botões repetidos quando a ação já estiver no menu de três pontos.
- Cancelar e Salvar no final do formulário, sem acompanhar a rolagem e sem uma caixa em volta.
- Ocultar barras cinzas do menu e da seleção de listas, preservando a rolagem.
- Distinguir status com texto e, quando útil, ícone; cor sozinha não basta.
- Manter os fluxos do cliente separados das melhorias administrativas.
- Concentrar cada processo em um local principal; usar atalhos contextuais nas outras telas.

## Referências avaliadas

| Referência | Aproveitar | Limite de aplicação |
| --- | --- | --- |
| [web-development-design-ops](https://github.com/clfigueiredo/hermes-infra-skills/tree/main/.hermes/skills/forumtelecom/web-development-design-ops) | Inventário do projeto, direção visual, responsividade, acessibilidade e validação no navegador. | Adaptar ao Laravel e Blade existentes; recursos de marketing e SEO não são prioridade nas telas autenticadas. |
| [UI Skills](https://github.com/ibelick/ui-skills) e [playbook](https://www.ui-skills.com/playbook) | Hierarquia visual, contraste, números tabulares, foco visível, estados vazios e formulários claros. | Avaliar cada skill individualmente. Compatibilidade de framework e tamanho das áreas de toque precisam ser conferidos. |
| [premium-frontend-ui](https://github.com/github/awesome-copilot/blob/main/skills/premium-frontend-ui/SKILL.md) | Identidade visual intencional, tipografia, desempenho e respeito a movimentos reduzidos. | Não adotar integralmente preloaders, cursores personalizados, botões magnéticos, rolagem interceptada ou animações contínuas no painel. |
| unbound-rpz, recebida no arquivo unbound-rpz.zip | Operação DNS verificável, preservação de DNSSEC e RPZ, medições e avaliação de versões. | É referência operacional; não define o layout nem autoriza mudanças futuras nos DNS. |

A skill web-development-design-ops foi instalada. As referências UI Skills e premium-frontend-ui foram avaliadas como material de orientação; não foram instaladas como pacotes adicionais.

## Direção proposta para o login

Uma entrada com identidade de produto de infraestrutura: azul escuro, destaque em ciano, boa tipografia e uma composição limpa. Em desktop, combinar uma área curta de apresentação do RPZ com o formulário de acesso. No celular, priorizar o formulário e reduzir os elementos decorativos.

O formulário deve manter e-mail, senha, opção de permanecer conectado, entrada e acesso ao cadastro. Campos e mensagens devem ter tamanho legível e espaço suficiente para interação por toque. Uma ação de mostrar senha pode ser considerada, com rótulo acessível e comportamento verificável.

Não exibir indicadores inventados de disponibilidade, bloqueios ou clientes. Textos sobre proteção devem corresponder às capacidades comprovadas do sistema. Não depender de fontes externas, vídeos ou bibliotecas de animação para permitir o acesso.

## Validação para a futura implementação

1. Preservar o envio para a rota de autenticação, CSRF, mensagens de erro e limite de tentativas.
2. Conferir preenchimento por gerenciadores de senha, autocomplete, teclado, foco e leitura dos rótulos.
3. Verificar contraste, zoom e larguras de 320, 390, 768 e 1440 pixels.
4. Conferir temas claro e escuro e a preferência por movimentos reduzidos.
5. Executar os testes de autenticação e revisar os estados de erro e envio no navegador.
6. Preparar a versão revisável e publicar dentro do escopo autorizado para o trabalho no login.

## Direção para simplificação funcional

- Detalhes da fonte: resumo, controles próprios e atalhos; evitar repetir listagens completas de servidores e domínios.
- Domínios: central com filtro por fonte e importação em lote para fontes que permitem edição manual.
- ANATEL: manter um único caminho de recebimento, processamento e revisão antes da publicação.
- Vínculos: edição do servidor como caminho principal; edição da fonte preservada para a seleção em lote de servidores.
- Configuração RPZ: detalhes do servidor concentram as instruções de instalação; a empresa preserva a visão agregada das ACLs.

## Referências operacionais

A avaliação do AnaBlock e da issue oficial do Unbound está em [Referências operacionais de Unbound e RPZ](REFERENCIAS-UNBOUND-RPZ.md). Esses assuntos não fazem parte do design do login.
