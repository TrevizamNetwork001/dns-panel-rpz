# Referências operacionais de Unbound e RPZ

Referências avaliadas em 10 de novembro de 2026 para orientar melhorias e diagnósticos futuros. Nenhum script de terceiros foi executado e nenhum resolvedor de cliente foi modificado nesta avaliação.

## Atualização por versão no AnaBlock

O [anablock_bind9](https://github.com/remontti/anablock_bind9) contém scripts para BIND9 e Unbound que consultam uma versão remota e baixam domínios para produzir uma zona RPZ.

Aproveitamento possível: consultar uma versão antes de baixar conteúdo que não mudou, após conferir o contrato, a confiabilidade e a disponibilidade da API. Isso ainda não foi integrado ao painel.

O [script examinado](https://github.com/remontti/anablock_bind9/blob/main/anablock_bind9.py) grava a versão antes de concluir a atualização da zona, usa serial diário, grava a zona diretamente e reinicia o DNS. Não copiar esses comportamentos para o painel: manter candidatos validados, serial por mudança efetiva, preservação da versão funcional e isolamento por empresa. Uma falha depois de salvar a versão pode impedir uma nova tentativa enquanto a versão remota não avançar.

## Recarga de configurações RPZ na issue 1021

A [issue oficial 1021](https://github.com/NLnetLabs/unbound/issues/1021) relata que, no Unbound 1.19.1 em FreeBSD, alterar `rpz-cname-override` e executar `unbound-control reload` mantinha o destino anterior. O relato é de março de 2024 e a issue está encerrada.

No [comentário do mantenedor](https://github.com/NLnetLabs/unbound/issues/1021#issuecomment-1988734418), a correção no código e um teste são confirmados. Esse comentário não identifica sozinho a primeira release ou o pacote de distribuição que incorporou a correção.

Aplicabilidade ao projeto: o gerador do painel coloca o destino CNAME no conteúdo da zona. Os trechos de configuração do painel não utilizam `rpz-cname-override`. Isso não revela configurações adicionais existentes nos DNS dos clientes.

Se um cliente apresentar o mesmo sintoma, conferir a versão e o pacote realmente em execução, patches da distribuição, configuração efetiva e release que contém a correção. Reproduzir em ambiente isolado antes de recomendar uma mudança operacional. O relato antigo não confirma um defeito nos resolvedores atuais, nem autoriza reiniciá-los como medida genérica.
