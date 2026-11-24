# Manual de integração do DNS Panel RPZ

Este guia mostra quais endpoints HTTP estão disponíveis, como consultar os feeds e como interpretar erros de acesso.

- [O que o painel suporta](#o-que-o-painel-suporta)
- [Onde copiar a URL](#onde-copiar-a-url)
- [Como fazer a primeira consulta](#como-fazer-a-primeira-consulta)
- [Autenticação e permissões](#autenticação-e-permissões)
- [Como resolver erros](#como-resolver-erros)

## O que o painel suporta

Use o endereço HTTPS da sua instalação como base e acrescente o caminho da tabela.

| Método e caminho | Uso | Resposta | Acesso |
| --- | --- | --- | --- |
| `GET /rpz/{identificador}.zone` | Distribuir regras de bloqueio para Unbound | Arquivo de zona DNS | Token do servidor ou identificador da empresa, conforme as regras de IP e licença |
| `GET /mikrotik/{identificador}.hosts` | Consultar a lista para MikroTik | Arquivo de texto no formato hosts | Token do servidor ou identificador da empresa, conforme as regras de IP e licença |
| `GET /dominios?dominio=exemplo.com` | Pesquisar um domínio pelo painel | Página HTML | Sessão do painel |
| `GET /listas` | Ver as listas disponíveis para a conta | Página HTML | Sessão do painel |
| `GET /empresas` | Listar empresas | Página HTML | Sessão de administrador |
| `GET /configuracoes` | Abrir as configurações e selecionar **Integrações** | Página HTML | Sessão de administrador |

Os dois feeds permitem automatizar o download das listas. A aba **Integrações** mostra o estado das sincronizações e oferece um atalho para **Gerenciar fontes**. Abra **Configurações → Integrações**. O código atual não registra a rota `/integracoes`.

## API REST do RPZ

Use HTTPS com `Accept: application/json` e `Authorization: Bearer <credencial>`. As empresas acessam o próprio cadastro a partir de **Integrações API**. Nessa página, o contrato OpenAPI está disponível em `/integracoes/openapi.json`. A credencial administrativa do Hermes é gerenciada em **Configurações → Integrações → Hermes Agent**.

| Método e caminho | Uso |
| --- | --- |
| `GET /api/v1/listas`, `/api/v1/bloqueios`, `/api/v1/servidores` | Consultar dados da empresa |
| `GET /api/v1/publicacao` | Consultar o estado da publicação |
| `GET /api/v1/operacao/empresas` | Buscar empresas com a credencial do Hermes |
| `GET /api/v1/operacao/empresas/{empresa}/sincronizacao` | Consultar a publicação e o estado por servidor |
| `GET /api/v1/operacao/empresas/{empresa}/listas/{lista}/conteudo?page=1` | Consultar conteúdo de uma lista manual em páginas de 25 regras |
| `POST /api/v1/operacao/empresas/{empresa}/listas/{lista}/solicitacoes-dominio` | Preparar inclusão ou remoção de um domínio em lista manual própria da empresa |
| `POST /api/v1/operacao/solicitacoes-excecao/{id}/confirmacao` | Confirmar uma solicitação pelo plugin do Telegram com a credencial de confirmação e o operador autorizados |

Para consultar a sincronização, por exemplo:

```bash
curl --fail-with-body -H 'Accept: application/json' \
  -H "Authorization: Bearer ${RPZ_OPERATIONS_TOKEN}" \
  "${RPZ_API_URL}/operacao/empresas/42/sincronizacao?page=1"
```

Defina `RPZ_API_URL` com a URL HTTPS que termina em `/api/v1`. Se receber `401` ou `403`, confira a credencial e as permissões. Para `404`, confira o caminho e os IDs. Para `422`, leia os erros dos campos na resposta. Para `429`, aguarde antes de repetir a chamada.

Consulte o [guia do Hermes](../integrations/hermes-rpz/README.md) para diagnóstico, exceções e vínculos de listas.

## Onde copiar a URL

- **Por servidor**. Abra **Servidores → selecione o endpoint → Configuração técnica** e copie a URL indicada na configuração do tipo de DNS escolhido.
- **Por empresa**. Abra **Empresas → selecione a empresa → URL RPZ da empresa → Copiar URL**. Para MikroTik, confira o endereço na configuração técnica do endpoint correspondente.

Copie o endereço completo para usar a extensão e o identificador corretos. Trate URLs com token como credenciais e evite incluí-las em capturas de tela, logs compartilhados ou chamados.

## Como fazer a primeira consulta

**Navegador.** Cole a URL completa do feed. O navegador pode exibir o conteúdo ou oferecer o arquivo para download. Faça o teste de um endereço IP autorizado para esse feed.

**Postman.** Crie uma requisição `GET`, cole a URL e selecione **No Auth**. O identificador já está no caminho da URL. O IP de origem da requisição precisa atender às regras do feed.

**Terminal.** Execute no servidor que vai consumir a lista. Substitua o valor de exemplo pela URL copiada do painel.

```bash
curl --fail --show-error --output lista.zone 'COLE_A_URL_RPZ_COMPLETA'
curl --fail --show-error --output lista.hosts 'COLE_A_URL_MIKROTIK_COMPLETA'
```

Uma resposta `200` confirma que o endpoint entregou o conteúdo. Confira o arquivo baixado antes de configurar o equipamento. Para usar a lista regularmente, siga as instruções de Unbound ou MikroTik na [seção sobre distribuição](../README.md#distribuicao).

## Autenticação e permissões

- **Feed empresarial.** O identificador da empresa vai no caminho da URL. A empresa precisa estar ativa e com licença ativa. O IP de origem deve corresponder a uma regra de IP ativa de pelo menos um servidor ativo da empresa. Se o equipamento usa IPv4 e IPv6, verifique qual endereço de saída chega ao painel.
- **Feed por servidor.** O token do servidor vai no caminho da URL. O servidor e a empresa precisam estar ativos e a empresa precisa ter licença ativa. Quando a restrição de IP estiver habilitada, use um endereço de origem autorizado.
- **Páginas do painel.** Faça login no navegador. As páginas administrativas exigem uma conta com papel de administrador. A sessão do navegador e o acesso ao feed seguem regras próprias.

## Como resolver erros

| Resposta | O que verificar |
| --- | --- |
| `403 Forbidden` ao abrir o feed empresarial | Use o IP de saída do equipamento e confirme que há uma regra ativa para ele em um servidor ativo da empresa. |
| `403 Forbidden` em **Configurações** | Confirme que a sessão atual é de uma conta administradora. Para a aba, abra `/configuracoes` e selecione **Integrações**. |
| `404 Not Found` no feed | Confira URL, extensão, identificador, status do servidor e da empresa e validade da licença. O feed por token também pode devolver `404` quando o IP é recusado. |
| `429 Too Many Requests` no feed | Aguarde antes de repetir. Cada rota de feed tem limite configurado de 60 requisições por minuto. |
| `503 Service Unavailable` no feed | Confira se a entrega de feeds está habilitada na instalação e se o serviço está disponível. |
| Redirecionamento para o login | Abra o caminho no navegador com uma sessão válida do painel. |

## Referências

- [Rotas HTTP do projeto](../routes/web.php)
- [Interface de endpoints e servidores](INTERFACE-ENDPOINTS.md)
- [Manual de incidentes](../RUNBOOK.md)
