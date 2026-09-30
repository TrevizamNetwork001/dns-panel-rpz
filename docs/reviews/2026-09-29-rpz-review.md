# Revisão de RPZ, SOA e NOTIFY — 29/09/2026

## Resultado e situação operacional

A revisão encontrou defeitos de código e de implantação. Não se deve atribuir a falta de atualização exclusivamente ao IPv6 ou a firewall remoto.

As correções desta revisão foram implantadas nos serviços permanentes em 30/09/2026, mantendo o piloto restrito ao **DNS-03 (`server:15`), laboratório**. Não foram criados commits ou executado push nesta revisão. Nenhum rollout para DNS-01/DNS-02 foi executado.

A suíte completa passou: **447 testes, 2.160 assertions**, em cópia isolada, banco SQLite em memória, rede externa desligada, com `named-checkzone` disponível. Resultado preservado em [2026-09-29-rpz-tests.txt](2026-09-29-rpz-tests.txt).

A imagem candidata `dns-panel-rpz-app:rpz-review-20260929` foi construída sem rede a partir de imagens locais; o autoload autoritativo foi regenerado e as classes novas foram carregadas com sucesso. Ela foi usada uma vez para um diagnóstico IPv4 limitado ao DNS-03: **três tentativas, nenhuma resposta NOTIFY correspondente recebida** (`unconfirmed`). O teste não montou banco nem volumes da aplicação. A imagem foi construída antes da formatação final automática; a diferença posterior é de estilo PHP.

## Responsabilidade e correção das conclusões anteriores

O assistente executou nesta conversa a ativação do worker, o filtro do piloto e os diagnósticos. A validação inicial foi insuficiente para comprovar o fluxo completo até o Unbound. O registro de envio adicionado na primeira correção estava em nível `info`, enquanto produção usa `LOG_LEVEL=error`; portanto não forneceu a evidência necessária no teste seguinte.

Aceitação pelo socket local **não comprova entrega**, e ACK DNS **não comprova instalação da zona**. A revisão passa a distinguir explicitamente esses resultados. O erro de IPv6 não explica, por si só, a ausência de resposta em IPv4.

Os commits do início do dia já estavam no repositório quando esta conversa começou. Esta revisão registra o que foi observado no código e nas ferramentas; não reconstrói autoria ou validações de sessões anteriores.

## Linha do tempo observada (America/Sao_Paulo)

| Evento | Evidência |
|---|---|
| Antes da ativação | Worker real: `primary_enabled=false`, artefatos estáticos habilitados. O listener SOA estar habilitado não habilitava o envio no worker. |
| 22:50:38 | `carga-teste-000003.rpz-load-test.example` desativado na lista 20, vinculada ao DNS-03. |
| 22:50:44 | Versão 50 / serial 25 publicada; domínio ausente do arquivo. |
| Aproximadamente 23:11 | Somente o container `queue` foi recriado com a imagem `notify-pilot`, flag antiga habilitada e IDs permitidos `15`. |
| 23:12:09 | Diagnóstico: socket aceitou 44 bytes para IPv4 do DNS-03; IPv6 retornou `Network unreachable`. Não houve validação de ACK nesse código. |
| 23:13:55 | `carga-teste-000004.rpz-load-test.example` desativado. |
| 23:14:01 | Versão 52 / serial 26 publicada; domínio ausente; worker concluiu o build. |
| Consulta após o teste | Último download registrado do DNS-03 ainda era revisão 25, às 22:52:31. Isso é uma fotografia daquele momento, não uma afirmação sobre o estado atual do resolver. |
| Diagnóstico com a imagem revisada | Três NOTIFYs por IPv4; nenhuma resposta correspondente recebida dentro dos limites de espera. |

A desativação dos domínios foi feita pelo usuário. Os diagnósticos não reativaram nem alteraram domínios reais.

## Defeitos encontrados e correções

### 1. Envio desativado no processo correto

O mesmo `RPZ_PRIMARY_ENABLED` controlava listener e envio. O Compose habilitava o listener, mas o worker que publicava a zona tinha a flag desligada. O notifier retornava silenciosamente.

**Correção revisada:** `RPZ_NOTIFY_ENABLED` independente de `RPZ_PRIMARY_ENABLED`, com lista explícita `RPZ_NOTIFY_SERVER_IDS`. O Compose candidato habilita somente o ID 15 e desabilita o envio IPv6 nesse piloto. Lista vazia não autoriza nenhum servidor. O serviço exige artefato estático e servidor/empresa ativos com licença válida.

Arquivos: `config/rpz.php`, `compose.yml`, `app/Services/RpzPrimaryNotifier.php`.

### 2. Envio sem confirmação, repetição e diagnóstico persistente

O envio antigo ignorava o retorno de `socket_sendto()`, suprimia avisos, fechava o socket imediatamente e não recebia ACK. As mensagens `info`/`warning` da primeira correção não apareciam no nível de log real.

**Correção:** transporte UDP separado com três tentativas por padrão, espera limitada de 1/2/4 segundos e validação de ID, opcode, QR, nome da zona, SOA/IN e RCODE. Socket UDP conectado restringe o endereço/porta de origem da resposta. Respostas estranhas não estendem o prazo. Recusa não é sucesso.

Cada destino gera auditoria persistente: `rpz.notify.acknowledged`, `.unconfirmed`, `.rejected` ou `.failed`; ausência de destino individual habilitado gera `.skipped`. Falhas de agendamento geram `.dispatch_failed`. Não depende de `LOG_LEVEL` para consulta na auditoria.

O aviso passa a ser um job separado (`NotifyRpzZone`), para a espera de rede não segurar o lock de publicação. Jobs de versões superadas são ignorados.

Arquivos: `app/Services/RpzNotifyTransport.php`, `app/Services/RpzPrimaryNotifier.php`, `app/Jobs/NotifyRpzZone.php`, `app/Services/RpzZoneBuildService.php`.

### 3. Destinos individuais em CIDR ignorados

O código descartava qualquer endereço com `/`, incluindo `/32` IPv4 e `/128` IPv6.

**Correção:** aceita hosts individuais com ou sem esses prefixos, normaliza e deduplica os IPs; continua sem expandir redes inteiras. Falha num destino não impede a tentativa no próximo.

### 4. SOA anunciando versão não publicada

`RpzPrimaryResponder` anunciava `rpz_zones.last_serial`, atualizado antes da validação/publicação do arquivo. Um build com falha ou descartado podia anunciar uma versão indisponível.

**Correção:** usa `currentVersion.serial`, exige versão publicada da mesma zona e arquivo presente. Não anuncia versão estática quando o HTTP está em modo dinâmico.

### 5. Pacotes DNS incorretos aceitos pelo listener

O listener não restringia opcode, QR, tipo, classe ou nome da zona. Podia responder SOA para uma pergunta A, para outra zona ou até para um pacote que já era resposta.

**Correção:** somente pergunta DNS válida SOA/IN para a zona do painel. Codec compartilhado valida comprimentos, quantidade de perguntas, ponteiros, ciclos e limites do nome. Comparação de nome ignora diferenças de caixa.

Arquivos: `app/Services/RpzDnsMessage.php`, `app/Services/RpzPrimaryResponder.php`.

### 6. IP compartilhado selecionando servidor arbitrário

A resolução pelo IP escolhia o primeiro servidor correspondente, mesmo que duas ACLs casassem com o mesmo endereço.

**Correção:** responde somente quando existe exatamente um servidor ativo correspondente. Caso ambíguo é rejeitado. Isso evita serial de outra zona, mas não adiciona suporte a múltiplos servidores atrás do mesmo NAT.

### 7. Zona empresarial desatualizada após mudanças no servidor

`markForServer()` marcava só a zona do servidor. Porém vínculos de lista, status, criação e remoção de servidor alteram também o conjunto agregado da empresa. Transferência de servidor entre empresas precisava invalidar ambas.

**Correção:** marca servidor e empresa; na transferência marca também a empresa anterior. Remoções de servidor/lista e limpeza por inatividade capturam os vínculos antes, mas disparam a reconstrução depois da exclusão. Isso corrige também a execução imediata pela fila síncrona.

Arquivos: `RpzDirtyMarker`, `ServidorController`, `ListaController`, `ServidoresInativosCleaner`.

### 8. Primeiro build sem pendência durável e sem recuperação periódica

Se não existisse linha em `rpz_zones`, o dirty marker só despachava o job. Se ele se perdesse, não havia pendência persistida. Não havia reconciliação agendada dos marcadores existentes. Builds que retornavam `failed` também podiam constar como job concluído.

**Correção:** cria o marcador antes do dispatch, inclusive no primeiro build; despacha após commit quando há transação; adiciona `rpz:build --dirty` a cada cinco minutos; erro de build faz o job falhar para permitir retry. Um servidor já removido é ignorado sem tentar criar uma FK inválida.

### 9. Janela entre verificação de obsolescência e publicação

A geração era consultada antes da publicação, mas outra alteração podia entrar entre essa consulta e a troca de `current_version_id`.

**Correção:** atualização condicional de geração e versão corrente dentro da transação de publicação. Se a condição não casar, descarta o candidato e agenda nova reconstrução, sem NOTIFY daquela versão.

### 10. Bootstrap estático resetando serial

O builder pré-criava uma zona com revisão/serial zero. O versionador então tratava a linha como existente e incrementava para 1, ignorando o callback de bootstrap legado.

**Correção:** placeholder com revisão zero e hash vazio usa o serial de bootstrap legado. Versões já inicializadas continuam incrementando. Não foi reescrito nenhum serial em produção.

### 11. Escrita parcial de arquivo sem tratamento

`fwrite()` não tinha seu retorno conferido. Falha durante renderização podia deixar candidato `building` e staging incompleto.

**Correção:** escrita integral com verificação do retorno, flush e tamanho real; fechamento de recursos, descarte do staging e estado `failed` em exceção durante geração/validação. Versão publicada anterior é preservada.

## Testes e método

1. Leitura dos serviços, controladores, jobs, configuração e testes existentes.
2. Consulta somente de leitura da configuração real, domínio informado, metadados, arquivo publicado e histórico de downloads. Nenhuma credencial foi copiada para este documento.
3. Seis testes de regressão foram executados contra o código anterior e falharam: serial não publicado, pergunta inválida, IP ambíguo, arquivo ausente, desvinculação de lista e remoção de servidor mantendo conteúdo empresarial antigo.
4. Testes locais UDP com processos separados: ACK válido, primeira mensagem perdida, ID incorreto, silêncio e resposta REFUSED. Não usam um resolver externo.
5. Testes de desativação real via controlador, duplicidade em outra lista, geração interrompida, lock de arquivo realmente mantido, corrida após a última consulta de geração, recuperação sem job original e bootstrap de serial.
6. Primeira execução ampliada: 143 de 144 testes passaram; único impedimento foi `named-checkzone` ausente na imagem inicial. Foi utilizada uma imagem local de testes que já continha o programa; não se removeu nem enfraqueceu essa validação.
7. Execução ampliada seguinte: 150 testes / 414 assertions aprovados.
8. Suíte completa, após acrescentar casos de nomes DNS malformados, exclusão de lista e modo HTTP dinâmico: **447 testes / 2.160 assertions aprovados**, 27,777 segundos.
9. Laravel Pint aplicado aos 24 arquivos PHP revisados. `git diff --check` sem erros.
10. Imagem candidata construída sem rede e autoload das classes novas conferido. Diagnóstico de rede externo somente no IPv4 do DNS-03: três tentativas sem ACK correspondente.

Os testes antigos cujos nomes prometiam validar falha/concorrência mas apenas repetiam um build foram renomeados para descrever o que realmente exercitavam. Os cenários reais agora têm testes próprios.

Ambiente: `/tmp/rpz-review-tests`, dependências de testes locais montadas somente para leitura, SQLite `:memory:`, sem volumes de produção. Imagem de testes: `dns-panel-rpz-test:bugfix-20260921` (PHP 8.4.12, `named-checkzone`). Artefatos temporários de teste não são usados pela aplicação.

## O que está e o que não está implantado

| Componente | Estado ao fim da revisão |
|---|---|
| Worker de produção | Imagem `dns-panel-rpz-app:notify-pilot`, com `RPZ_NOTIFY_ENABLED=true`, `RPZ_NOTIFY_SERVER_IDS=15`, IPv6 desativado no piloto e `queue:work --tries=3`. |
| Listener `rpz-primary` | Recriado com a imagem `dns-panel-rpz-app:local` construída a partir do código revisado; ouvindo UDP/53. |
| Aplicação HTTP e scheduler | Recriados com a imagem `dns-panel-rpz-app:local`; nginx reiniciado após a troca do IP interno do app. |
| Código no diretório de trabalho | Revisão corrigida e testada, com alterações ainda sem commit. |
| Imagem `rpz-review-20260929` | Usada na validação isolada antes do deploy; os serviços permanentes usam as imagens produzidas pelo Compose. |
| DNS-03 | Único destino autorizado nos testes de rede. Não houve acesso SSH nem alteração da configuração do Unbound durante a revisão. |

**Deploy executado:** `docker compose build app nginx`, `docker compose build queue`, `docker compose --profile cutover up -d` e `docker compose restart nginx`. O `docker compose config` isolado não pôde ser usado porque o `.env.docker` local não existe; o deploy efetivo usou `RPZ_ENV_FILE=/etc/dns-panel-rpz/app.env`. O novo `docker/Dockerfile.notify-pilot` copia `app`, `config` e `routes` e regenera o autoload, necessário para os novos jobs/classes.

## Implantação e reversão

- A revisão não adiciona migrations.
- Aplicação, worker, listener e scheduler foram implantados juntos; trocar só o worker em futuras versões não aplica as correções de mutação HTTP nem de resposta SOA.
- Guardar IDs das imagens e a configuração efetivamente usada antes de recriar qualquer serviço. Tags locais podem ser sobrescritas; não usá-las como única referência de rollback.
- Habilitar `RPZ_NOTIFY_ENABLED=true`, `RPZ_NOTIFY_SERVER_IDS=15` e `RPZ_NOTIFY_IPV6_ENABLED=false` somente no worker durante o piloto. `RPZ_PRIMARY_ENABLED` continua sendo a chave do listener; a flag estática deve estar ativa para SOA/NOTIFY.
- Validar configuração efetiva dentro dos processos, compatibilidade do autoload, fila, tarefa `rpz:build --dirty` e consulta SOA no laboratório. Depois executar uma inclusão e uma desativação sintéticas e correlacionar publicação, auditoria NOTIFY, SOA e download.
- Para reversão operacional, pausar/drainar jobs novos antes de voltar a uma imagem sem `NotifyRpzZone`; caso contrário o worker antigo pode não desserializar jobs novos. Repor conjuntamente imagens e flags anteriores. Não reverter serial/publicação do banco para um valor antigo.
- Como a aplicação HTTP roda em imagem e o nginx resolve seu endereço interno, seguir o procedimento do RUNBOOK para evitar 502 após recriação da aplicação.

## Limitações e pendências explícitas

- Ainda falta capturar simultaneamente a saída no painel e a entrada no DNS-03, conferir o IP de origem efetivo e a aceitação pelo Unbound. `unconfirmed` pode decorrer de perda/bloqueio de ida, de volta ou de recusa sem resposta. Não comprova defeito no firewall de uma hospedagem específica.
- IPv6 segue com falha de rede conhecida. Nesta revisão, o piloto candidato usa somente IPv4 para NOTIFY.
- Um ACK só confirma a transação NOTIFY. Para concluir a validação funcional, confirmar download e aplicação da revisão no Unbound.
- Destinos continuam derivados da ACL HTTP; NAT ou endereço diferente de escuta do resolver podem exigir cadastro próprio de destinos NOTIFY numa evolução futura.
- O primary continua limitado a zonas por servidor e a identificação não ambígua por IP. Não há suporte implementado a `company:<id>` no primary/NOTIFY, nem a múltiplas zonas diferenciadas por nome atrás do mesmo IP.
- Reconciliação recupera marcadores persistidos, mas as mutações de todos os produtores e o dirty marker ainda não formam uma única transação universal. Queda exatamente entre a alteração de conteúdo e a marcação exige reconciliação operacional mais ampla; um outbox transacional seria uma evolução adicional.
- Publicação e agendamento do NOTIFY não têm outbox transacional. Queda entre commit da publicação e enqueue pode perder o aviso; o refresh periódico continua necessário.
- A retenção de versões antigas e a política de wrap do serial não foram redesenhadas. O serial continua falhando explicitamente no limite de 32 bits; o bootstrap por CRC pode começar perto desse limite.
- O código mantém validação estrutural como fallback quando `named-checkzone` não existe em produção. Os testes desta revisão usaram o validador real.
- Não foi feita auditoria completa de todo o painel, nem benchmark de throughput sob ataque ao socket UDP. O escopo foi o fluxo RPZ criado/alterado no dia e suas integrações.

## Referências de protocolo

- [RFC 1996](https://www.rfc-editor.org/rfc/rfc1996.html), especialmente 3.3, 3.6, 3.10, 3.11 e 4.5: confirmação, repetição, origem e significado do NOTIFY.
- [Documentação oficial do Unbound — RPZ](https://unbound.docs.nlnetlabs.nl/en/latest/manpages/unbound.conf.html#rpz-options): `primary`, `url` e `allow-notify`.
