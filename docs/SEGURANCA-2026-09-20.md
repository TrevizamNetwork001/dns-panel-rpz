# Lista manual de segurança — 20/09/2026

Escopo autorizado: lista global manual `Segurança — Malware / C2 / IoT`, seis entradas, vinculada aos servidores ativos já vinculados às listas de segurança existentes. Novos servidores precisam de vínculo explícito como nas demais listas.

| Domínio | Justificativa | Regra |
| --- | --- | --- |
| 3gipcam.com | IOC histórico Mirai/Gayfemboy, FortiGuard Labs | Domínio e subdomínios |
| cross-compiling.org | C2 histórico Mirai/Gayfemboy, FortiGuard Labs | Domínio e subdomínios |
| i-kiss-boys.com | C2 histórico Mirai/Gayfemboy, FortiGuard Labs; ThreatFox Mirai/Katana | Domínio e subdomínios |
| furry-femboys.top | C2 histórico Mirai/Gayfemboy, FortiGuard Labs | Domínio e subdomínios |
| twinkfinder.nl | C2 histórico Mirai/Gayfemboy, FortiGuard Labs | Domínio e subdomínios |
| wpad.domain.name | Risco histórico de WPAD/proxy documentado pela D-Link e ICANN | Somente FQDN exato |

Referências:
- FortiGuard Labs, 22/08/2025: https://www.fortinet.com/blog/threat-research/iot-malware-gayfemboy-mirai-based-botnet-campaign
- ThreatFox: https://threatfox.abuse.ch/ioc/1543919/ — IOC histórico, expirado em 10/08/2026 e não exportado pelo serviço. Não é evidência de atividade atual.
- D-Link: https://supportannouncement.us.dlink.com/announcement/publication.aspx?name=SAP10268
- ICANN: https://www.icann.org/en/system/files/files/root-cause-analysis-wpad-18jan23-en.pdf

O bloqueio foi autorizado com base nesse histórico; não houve confirmação de atividade maliciosa atual. Nomes estranhos, ausência de HTTPS e presença em infraestrutura compartilhada não foram usados como prova de malware.

`wpad.domain.name` sai do conjunto ativo da lista ANATEL e recebe exclusão exata nessa lista para evitar reinclusão. O gerador RPZ não emite wildcard para esse FQDN. Não se cadastra `wpad`, `domain.name`, nem WPAD interno.

`jhkkjkj.com`, `weerrhoop.cc` e `ojsbdjn.com` permanecem inativos e com exclusões exatas ativas na ANATEL. A anotação MONITORAR registra a decisão de revisão; não representa monitoramento automático de tráfego nem certificação de legitimidade.

Validação anterior à publicação: 45 testes, 121 assertions; zona dos seis nomes validada com `named-checkzone` no host. O teste existente que invoca esse utilitário não roda na imagem PHP por ausência do executável; a verificação de sintaxe foi feita separadamente. Não há alteração do refresh de 1 hora.

Publicação concluída: lista ID 19, seis domínios ativos, vinculada aos sete DNS ativos existentes (IDs 2, 6, 8, 9, 10, 11 e 12). Verificadas as regras da lista e a presença dos seis domínios nas consultas RPZ de cada servidor. Exclusões dos três domínios preservadas. Login HTTP 200 após publicação. A aplicação nos DNS remotos depende da próxima sincronização; não foi confirmada remotamente.
