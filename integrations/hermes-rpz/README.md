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
