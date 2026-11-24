# Piloto do Hermes com o RPZ

O adaptador MCP roda na VPS, sob a mesma conta de sistema que executa o gateway do Hermes. Ele usa a API do RPZ por HTTPS para consultar listas, servidores, publicação da empresa e regras de um domínio exato.

## Instalação na VPS

1. Confirme que o ambiente do RPZ já oferece o menu **Integrações**. Entre como usuário da empresa piloto e gere uma credencial com escopo **leitura** (`read`).
2. Copie este diretório para `~/hermes-rpz` na VPS. Com Python 3.10 ou mais recente, crie o ambiente:

   ```bash
   cd ~/hermes-rpz
   python3 -m venv .venv
   .venv/bin/python -m pip install -r requirements.txt
   ```

3. Crie o arquivo `~/hermes-rpz/rpz.token` com modo `600`, propriedade da conta que executa o gateway e apenas o token em uma linha. Insira a credencial diretamente no arquivo com um editor, fora do repositório e do Telegram.
4. Acrescente a entrada abaixo à seção existente `mcp_servers` do perfil do gateway em `config.yaml`. Use caminhos absolutos e o endereço HTTPS real do painel.

   ```yaml
   mcp_servers:
     rpz_piloto:
       command: /home/operador/hermes-rpz/.venv/bin/python
       args:
         - /home/operador/hermes-rpz/server.py
       env:
         RPZ_API_URL: https://painel.exemplo.com/api/v1
         RPZ_TOKEN_FILE: /home/operador/hermes-rpz/rpz.token
       tools:
         prompts: false
         resources: false
   ```

5. Recarregue o MCP com `/reload-mcp` ou reinicie o gateway do perfil. Use um grupo privado da equipe e restrinja o acionamento a operadores identificados por ID do Telegram. As respostas publicadas ficam visíveis aos participantes do grupo.

## Validação pelo Telegram

Envie uma mensagem ao bot:

> Qual a situação da empresa piloto no RPZ? Liste as listas e os servidores. Informe o serial publicado e, para cada servidor, a última confirmação disponível.

Confira os resultados no painel. Se houver mais páginas, peça a próxima ou o total registrado. O status de publicação se refere à zona, enquanto a entrega e a confirmação são observações por servidor. A busca por domínio retorna regras cadastradas para aquele nome exato nas listas visíveis. Para confirmar aplicação no DNS, use a confirmação do servidor e, quando necessário, teste com o resolvedor.

[Configuração MCP do Hermes](https://hermes-agent.nousresearch.com/docs/user-guide/features/mcp) · [Controle de acesso no Telegram](https://hermes-agent.nousresearch.com/docs/user-guide/messaging/telegram)

## Consulta administrativa de qualquer empresa

A consulta administrativa usa a API em `/api/v1/operacao` e uma credencial própria. A URL base do MCP continua terminando em `/api/v1`. Somente o perfil dos operadores que podem ler dados de qualquer empresa deve receber essa credencial. Use um chat ou tópico com audiência autorizada.

Entre como administrador do RPZ e abra **Configurações → Integrações → Hermes Agent**. Gere uma credencial de leitura e coloque o token exibido diretamente no arquivo privado configurado em `RPZ_TOKEN_FILE`. O RPZ mantém somente o hash da credencial. A mesma seção permite revogar ou substituir o token. Recarregue o MCP depois de salvar o arquivo na VPS.

Com a credencial administrativa, o MCP disponibiliza `buscar_empresas` e `ver_sincronizacao`. Em caso de nomes ambíguos, escolha a empresa antes de consultar. Exemplo de teste no Telegram:

> Hermes, consulte o RPZ da empresa SBC NET e veja a última sincronização de cada servidor, incluindo a confirmação do serial quando existir.

Pendência futura: publicar a documentação de uso da API administrativa com exemplos de autenticação, consultas, paginação e respostas de erro.

## Exceção por empresa com confirmação no Telegram

O MCP administrativo oferece `diagnosticar_dominio` e `preparar_excecao`. O diagnóstico mostra listas candidatas e exceções aplicáveis na configuração. A prévia identifica empresa, domínio, abrangência de subdomínios e motivo, e expira em dez minutos. Preparar a solicitação não altera regras RPZ.

O plugin em `plugin/` deve ser instalado como `rpz-approval` no perfil de operação. Habilite o comando de usuário `rpz-confirmar` no grupo autorizado conforme o controle de comandos do Hermes. O operador pode responder `confirma` diretamente à prévia enviada pelo próprio bot. O comando `/rpz_confirmar <solicitação>` permanece disponível como alternativa. O gateway fornece os IDs do remetente, chat e tópico ao comando.

- Configure no RPZ `RPZ_TELEGRAM_CHAT_ID`, `RPZ_TELEGRAM_THREAD_ID` e `RPZ_TELEGRAM_OPERATORS`, um objeto JSON que associa IDs de usuários do Telegram aos IDs dos administradores no RPZ.
- Gere uma credencial de confirmação com prefixo `rpza_` seguido de 64 caracteres hexadecimais aleatórios. Configure somente o SHA-256 do token completo em `RPZ_TELEGRAM_CONFIRMATION_HASH`.
- Na VPS, mantenha a credencial em arquivo privado. O arquivo `approval.json` ao lado do plugin define `api_url` (terminada em `/api/v1`), `chat_id`, `thread_id` e `token_file` (caminho absoluto). O comando de confirmação usa a credencial. O servidor MCP usa apenas sua credencial administrativa de consulta.
- Aplique a migração de solicitações de exceção antes de carregar a versão nova do MCP.

Para consultar exceções, use `listar_excecoes` com o ID da empresa. Para remover uma exceção existente, use `preparar_remocao_excecao` e confirme a prévia pelo mesmo comando do Telegram. O RPZ vincula a solicitação ao ID exato da exceção para preservar uma eventual substituição feita depois da prévia. O recibo informa o estado atual da zona. Consulte a sincronização da empresa para acompanhar entregas e confirmações posteriores. O Hermes apresenta os horários administrativos em `America/Sao_Paulo`.

## Vínculos de listas por servidor

Use `listar_vinculos_listas` para consultar os vínculos por servidor e `buscar_listas_disponiveis` para localizar listas compartilhadas ou próprias do cliente. Com `preparar_vinculo_lista`, selecione a empresa, um servidor, uma lista e a operação `vincular` ou `desvincular`. A prévia informa a lista, seu status e seu número de domínios ativos. O operador confirma respondendo `confirma` à mensagem da prévia no tópico autorizado. A confirmação registra a alteração na auditoria e marca a empresa e o servidor para republicação.

## Registro de implantação e retomada — 21/11/2026

### Validado em produção

- Busca de empresas pelo nome e consulta da sincronização por servidor no tópico **Gestão de Clientes**, perfil `gestao-de-clientes`. A resposta diferencia publicação do painel, entrega da revisão e confirmação do serial pelo resolvedor.
- Diagnóstico de domínio por empresa com listas candidatas e indicação de exceção aplicável. Na SpeedFiber, o diagnóstico de `wpad.domain.name` encontrou a lista **Segurança — Malware / C2 / IoT**, sem exceção. A aplicação no resolvedor não foi testada.
- Listagem, criação e remoção de exceções por empresa. O ciclo de teste com `example.invalid` na SpeedFiber foi concluído e a exceção foi removida. A auditoria confirmou o operador, e a consulta posterior registrou a zona publicada separadamente das entregas e confirmações.
- Listagem dos vínculos entre listas e servidores, busca de listas disponíveis e preparação de vínculo e desvínculo. O teste no **DNS-01 da SBCNET LTDA** adicionou e retirou a lista **Hermes Teste de Vínculo** via Telegram. Os dois eventos ficaram auditados. Foi conferido que **DNS-01** e **DNS-02** permaneceram com as mesmas sete listas ativas originais cada um. A lista de teste está vazia, exclusiva da SBCNET LTDA e sem vínculos.
- O operador pode responder `confirma` diretamente à mensagem da prévia. O RPZ valida o operador e a solicitação, que expira em dez minutos. O comando `/rpz_confirmar` é a alternativa. A prévia e o recibo mostram nomes da empresa, ícones e horários em Brasília com segundos. Quando uma sessão anterior à implantação desconhecer uma ferramenta nova, use `/new` no tópico para carregar uma sessão atualizada.

### Referências operacionais

- RPZ em produção na imagem `dns-panel-rpz-app:hermes-vinculos-escrita-20261121`, com o override `/etc/dns-panel-rpz/hermes-vinculos-escrita.compose.yml`. Foi feito backup SQLite com verificação de integridade antes da migração de solicitações de vínculo.
- O gateway Hermes usa o adaptador `rpz_piloto` e o plugin `rpz-approval` no perfil **Gestão de Clientes**. A credencial de confirmação é separada da credencial usada pelo MCP para consulta e prévia.
- A chave SSH temporária de manutenção ficou ativa a pedido do operador enquanto a integração estiver em refinamento. A revogação deve ser feita quando o operador solicitar o encerramento desse acesso.

### Consulta de conteúdo de listas manuais

Use `buscar_listas_disponiveis` para localizar o ID e `consultar_lista_manual` para consultar origem, status e domínios de uma lista manual compartilhada ou própria da empresa. A consulta devolve 25 domínios por página, incluindo o estado de cada regra. A data da lista se refere ao cadastro da lista e a data dos domínios corresponde à última atualização entre as regras atualmente cadastradas.

A ferramenta usa `GET /api/v1/operacao/empresas/{empresa}/listas/{lista}/conteudo` com a credencial administrativa de consulta e aceita `page`. Para validar no Telegram após a implantação, peça o conteúdo de **Hermes Teste de Vínculo** na **SBCNET LTDA**. O estado esperado da lista de teste é zero domínios.

### Alteração de domínios em listas manuais

Use `preparar_dominio_lista_manual` com o ID da empresa, o ID da lista, o domínio, a operação e o motivo. O fluxo atende listas manuais próprias da empresa. O Hermes apresenta a prévia com os servidores vinculados. Responda `confirma` à prévia no tópico autorizado. Após a confirmação, o RPZ registra a mudança na auditoria e sinaliza a lista para republicação.

Veja o [manual da API](../../docs/MANUAL-INTEGRACOES.md) para o contrato HTTP e um exemplo de consulta.
