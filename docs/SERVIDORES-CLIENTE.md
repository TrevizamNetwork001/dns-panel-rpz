# Servidores do cliente

Atualizado em 21/09/2026. Mudança de apresentação aplicada ao cadastro, edição e listagem de servidores, com acabamento revisado pelo usuário. Os endpoints e as regras existentes permanecem iguais.

## Comportamento

- A listagem mostra o resumo de cadastrados/ativos e as colunas Nome, Tipo, Listas, Última sincronização, Status e Ações. IPs públicos não aparecem nessa tabela. Editar fica visível; o menu secundário mantém detalhes e remoção.
- “Em dia” é o rótulo visual para o estado anteriormente apresentado como “Normal”; os critérios de sincronização não mudaram.
- Cadastro e edição compartilham Identificação, Configuração DNS e Listas habilitadas. IPs e seus avisos continuam nos campos de cadastro/edição. O fluxo existente de detalhes e liberação de IP permanece disponível.
- As listas disponíveis continuam vindo do controller. A pesquisa local filtra nome e descrição, ignorando acentos. Adicionar habilita o input oculto `lista_ids[]`; remover o chip desabilita esse input. O contador considera todas as listas disponíveis, independentemente do filtro.
- Após erro de validação, o formulário restaura `old('lista_ids')`. Uma seleção vazia continua vazia, sem reaplicar as associações anteriores. Somente o envio válido persiste as alterações.
- Exclusão usa o endpoint DELETE e o CSRF existentes, com confirmação. O botão fica no rodapé do card, mas aponta para um formulário separado por `form="delete-server-form"`, evitando formulários HTML aninhados.

## Arquivos

| Arquivo | Responsabilidade |
| --- | --- |
| `resources/views/servidores/index.blade.php` | Tabela, resumo, ações e estado de sincronização |
| `resources/views/servidores/form.blade.php` | Campos, cards e inclusão dos partials |
| `resources/views/servidores/partials/list-selector.blade.php` | Chips, catálogo, pesquisa e JavaScript local |
| `resources/views/servidores/partials/form-actions.blade.php` | Rodapé compartilhado do formulário |
| `resources/views/components/ui-icon.blade.php` | SVGs locais reutilizáveis |
| `resources/views/components/list-category-icon.blade.php` | Escolha visual do ícone pelo nome/categoria |
| `resources/views/layouts/app.blade.php` | Ícone de servidor na navegação |
| `resources/views/servidores/show.blade.php` | Indicador de status consistente nos detalhes do cliente |
| `public/assets/app.css` | Estilos efetivamente servidos pelo painel |
| `tests/Feature/ClientServersPresentationTest.php` | Renderização, ausência de IPs na tabela e seleção vazia após erro |

## Padrão visual

SVGs inline, sem CDN ou biblioteca JavaScript adicional, com traço de 2 px. Navegação/títulos usam 18 px; campos, botões e ações pequenas, 16 px; status, 14 px. O indicador de status tem distância de 6 px do texto. Campos têm 40 px de altura, bordas de 10 px de raio e cores específicas para cada tema.

| Categoria | Ícone | Cor |
| --- | --- | --- |
| ANATEL | Prédio | Azul claro/neutro |
| Ameaças Gerais | Escudo | Ciano |
| Botnets e Controle Remoto | Rede | Vermelho |
| Malware e Vírus | Bug | Verde |
| Mineração de Criptomoedas | Picareta | Âmbar |
| Phishing e Golpes | Alerta | Âmbar |
| Ransomware | Cadeado | Violeta |
| Outras listas | Lista | Neutra |

A classificação é exclusivamente visual; não altera dados nem associações. Catálogo e chips usam o mesmo componente. Adicionar usa ciano e Adicionada usa verde. Os tokens de cor acompanham o tema claro.

## Validação e publicação

Validação final em contêiner isolado, sem rede, código somente para leitura e SQLite em memória: **86 testes e 468 assertions aprovados**. Filtro executado:

```bash
docker run --rm --network none --entrypoint php \
  -v /opt/dns-panel-rpz:/workspace:ro -w /workspace \
  dns-panel-rpz-app:local vendor/bin/phpunit --do-not-cache-result \
  --filter 'ClientServersPresentationTest|ServidorAllowedIpTest|ServidorIpPendenteTest|MikrotikHostsEndpointTest|AdminEndpointsPresentationTest|ClienteIsolamentoTest|ServidorListaAtividadeTest|LicencaNoExpiryTest|RpzEndpointTest|test_server_form_shows_description|test_server_page_and_list_index'
```

Também foi executada uma verificação temporária de JavaScript com DOM simulado: adicionar/remover, habilitação de inputs, contador, busca por descrição/sem acentos e resultado vazio. A renderização foi verificada por testes HTTP/Blade e compilação de views; não houve captura automatizada de navegador. O usuário revisou o acabamento na aplicação.

O teste `ListaDescricaoVisivelTest::test_client_dashboard_shows_what_each_list_is_for` falhou também com as views originais, pois espera conteúdo do dashboard fora deste escopo. A suíte completa não é declarada como aprovada.

As imagens finais da interface foram publicadas como `dns-panel-rpz-app:category-colors-20260921` e `dns-panel-rpz-web:category-colors-20260921`, também apontadas pelas tags `:local`. App e nginx foram recriados; views limpas/recompiladas; saúde dos contêineres, HTTP 200 e hash do CSS servido conferidos. Sem migrations ou alterações no banco.

Para futuras publicações, seguir o [RUNBOOK](../RUNBOOK.md#fazer-um-deploy): alterações de CSS precisam chegar às imagens app e nginx, pois o layout usa `filemtime` do CSS para a versão do asset. Mudanças de Blade exigem limpar/recompilar o cache de views. As imagens anteriores estão disponíveis para reversão operacional.

## Complemento: detalhes compartilhados e administração

Ainda em 21/09/2026, os detalhes do servidor passaram a usar o mesmo resumo de status com SVGs para admin e cliente, listas em modal lateral, histórico recente limitado a 5 eventos e configuração técnica recolhível. O gerenciamento de IPs continua exclusivo do admin. A listagem e a edição administrativas também foram modernizadas; o seletor e a listagem do cliente descritos acima permanecem próprios desse perfil.

A validação e as tags de publicação desta seção anterior são históricas. Para o estado mais recente, a verificação em navegador e as imagens posteriores, consulte [Interface de endpoints e servidores](INTERFACE-ENDPOINTS.md).
