# Empresas

Atualizado em 21/09/2026.

## Cadastro e edição

O administrador acessa `/empresas` e pode criar ou editar empresas. Além dos campos anteriores, o formulário aceita **Nome fantasia** (opcional, até 255 caracteres) e **Telefone** (opcional, até 32 caracteres). O telefone permite formatação, prefixo internacional e ramal, sem máscara obrigatória.

Os campos são persistidos em `empresas.nome_fantasia` e `empresas.telefone`. Podem ser limpos na edição; empresas anteriores à migration mantêm os valores nulos. A migration `2026_09_21_180000_add_contact_fields_to_empresas_table.php` foi aplicada após backup local e envio ao R2. Reverter essa migration remove os dados desses dois campos.

## Abrir uma empresa

O nome da empresa em `/empresas` abre `/empresas/{id}`. Essa tela administrativa agora compartilha o perfil atualizado da área do cliente: dados cadastrais, contato e resumo de licença com utilização de servidores. Nome fantasia e telefone exibem os valores salvos; o traço aparece somente quando não preenchidos.

O administrador pode editar a empresa e consultar a tabela de servidores com nome, tipo DNS, quantidade de listas, última consulta, situação de sincronização, status no painel e aviso de IP pendente. As ações **Ver detalhes** e **Editar** abrem as telas atuais de servidores.

**Novo servidor** passa `empresa_id` ao formulário de criação. O controller pré-seleciona a empresa e carrega suas listas disponíveis. A criação direta pelo menu continua permitindo escolher a empresa. Para clientes, a empresa continua definida pelo usuário autenticado, sem usar o parâmetro administrativo.

Os blocos de Fontes e Usuários permanecem disponíveis ao administrador. A seção **Distribuição RPZ empresarial** é recolhível e mantém URL, configuração, IPs autorizados e teste de ACL. Após um teste de ACL, abre automaticamente para apresentar o resultado.

Para vincular ou liberar uma ACL, use **Gerenciar ACL** na linha do servidor. O atalho abre diretamente **Restrição de IP → Gerenciar IPs**, onde o administrador pode adicionar IPv4/IPv6 ou redes CIDR, liberar IPs pendentes e remover regras. Informe o IP de saída do DNS do cliente. A URL empresarial considera os IPs ativos dos servidores ativos da empresa; o teste de ACL na tela da empresa apenas consulta a autorização, não cadastra regras.

O cliente continua vendo somente sua empresa e o resumo da licença. A edição permanece indisponível nesse perfil, com botão desabilitado e texto explicativo. As rotas administrativas continuam protegidas.

## Implementação

- `EmpresaController.php`, `Empresa.php` e `empresas/form.blade.php`: validação, persistência e formulário.
- `empresas/show.blade.php`: composição dos detalhes e seções administrativas.
- `empresas/partials/company-profile.blade.php`: perfil compartilhado entre admin e cliente.
- `empresas/partials/admin-servers.blade.php`: tabela responsiva de servidores da empresa, reutilizando os estilos atuais de endpoints.
- `ServidorController::create()`: seleção inicial da empresa no cadastro de servidor.

Os caminhos de views são relativos a `resources/views`; controllers estão em `app/Http/Controllers` e o model em `app/Models`.

## Validação

Suíte completa: **416 testes e 2.514 assertions aprovados**, em contêiner sem rede, SQLite em memória e imagem temporária com `bind-tools` para validar zonas com `named-checkzone`. Não foram ignorados testes. Cobertura inclui persistência e limites dos campos, renderização, isolamento entre empresas, edição restrita e seleção da empresa no cadastro de servidor.

Foram corrigidas expectativas antigas que ainda procuravam servidores individuais no perfil da empresa, listas no dashboard e descrições de listas não vinculadas no detalhe do servidor. Os testes agora verificam os locais atuais desses conteúdos e mantêm as verificações de autorização. Nenhuma lista de servidores ou fontes foi reintroduzida na área do cliente para atender a testes antigos.

## Publicação

Campos publicados na imagem `dns-panel-rpz-app:company-contact-20260921`. Revisão de apresentação e tela administrativa publicada com `dns-panel-rpz-app:company-fix-20260921`, também marcada como `local`, recriando somente o serviço `app` e renovando o cache Blade. Sem nova migration nesta revisão visual. Seguir o [RUNBOOK](../RUNBOOK.md#fazer-um-deploy) para próximas publicações.
