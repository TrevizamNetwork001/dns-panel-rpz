# Listas do cliente

Atualizado em 21/09/2026. Tela publicada em `/listas` para o perfil cliente.

## Apresentação e comportamento

A referência utilizada foi a imagem local `referencias/2041523f-6623-4e13-a2b9-80c8fb208b48.png`. A tela apresenta o título Listas, total de listas cadastradas, total de ativas e um aviso sobre a manutenção das listas.

Cada cartão mostra ícone colorido por categoria, nome, descrição quando preenchida, selo **Catálogo** ou **Própria** e quantidade de domínios ativos com separador de milhar em português. O layout se adapta a telas menores.

Decisões finais solicitadas pelo usuário:

- Não incluir busca.
- Ordenar por nome de A a Z, antes da paginação de 20 itens. Nomes iguais usam ID decrescente como desempate; a comparação segue a collation do banco.
- Manter os cartões apenas informativos: passar o mouse não muda sua aparência e clicar não abre outra tela. O cartão usa `div`, sem `href` nem manipulador de clique.

O estado vazio informa que as listas liberadas para a empresa aparecerão nessa tela. Os links de paginação continuam funcionando.

## Dados e permissões

A consulta mantém o catálogo (`empresa_id` nulo) e as listas da empresa autenticada. A quantidade de domínios considera apenas registros com `ativo = true`. O total de listas ativas considera `status = active`; a listagem mantém o comportamento anterior de exibir também listas inativas dentro do escopo permitido.

A remoção do link é uma mudança de apresentação. A rota de detalhes e suas verificações de acesso continuam existentes. O administrador continua usando a view de Fontes, com suas ações; a ordenação alfabética também permanece nesse perfil.

Não houve migrations nem alterações em ACL, licença, sincronização ou geração de zonas RPZ.

## Arquivos

| Arquivo | Responsabilidade |
| --- | --- |
| `app/Http/Controllers/ListaController.php` | Ordenação, paginação, escopo e seleção da view por perfil |
| `resources/views/listas/cliente.blade.php` | Cabeçalho, cartões informativos e estado vazio |
| `resources/views/listas/partials/cliente-styles.blade.php` | Estilos exclusivos da tela e adaptação para celular |
| `resources/views/components/list-category-icon.blade.php` | Ícone reutilizado por categoria |
| `tests/Feature/ClientAreaPresentationTest.php` | Apresentação, isolamento e ordem entre páginas |

## Validações realizadas

Antes do ajuste final que removeu o link e o efeito de hover, as suítes `ClientAreaPresentationTest` e `AdminSourcesPresentationTest` passaram com **13 testes e 79 assertions**, em contêiner temporário sem rede e banco SQLite em memória. O teste de ordenação usa 21 listas para verificar a passagem da primeira para a segunda página.

Após o ajuste final: `git diff --check` aprovado, templates Blade compilados com sucesso e `/login` respondendo **HTTP 200**. As suítes não foram repetidas após essa alteração de marcação e CSS. Não foi realizada inspeção automatizada em navegador nesta entrega; o usuário confirmou o visual após a primeira publicação.

## Publicação e manutenção

Foi criada a imagem `dns-panel-rpz-app:client-lists-20260921`, atribuída também à tag `dns-panel-rpz-app:local`. O serviço `app` foi recriado e o cache de views limpo e recompilado com `view:clear` e `view:cache`.

O ajuste final foi republicado usando a mesma tag. A imagem `dns-panel-rpz-app:before-client-lists-20260921` passou a representar a versão imediatamente anterior ao ajuste: já contém o novo visual, mas ainda tem cartões clicáveis. Portanto, essa tag não representa a tela antiga em tabela.

O Dockerfile e o script usados ficaram em `/tmp/rpz-client-lists/`; são arquivos temporários, não dependências permanentes do projeto. Os arquivos de implementação estão no repositório. Para publicações futuras, seguir o [RUNBOOK](../RUNBOOK.md#fazer-um-deploy). Os estilos desta tela estão no Blade, por isso esta entrega exigiu apenas reconstrução do app, sem mudança nos arquivos estáticos do nginx.
