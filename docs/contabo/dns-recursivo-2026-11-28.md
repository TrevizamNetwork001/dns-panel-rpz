# Módulo DNS Recursivo em produção

Registro de 27 e 28 de novembro de 2026. O painel passou a oferecer um módulo administrativo para cadastrar resolvedores, diagnosticar o Unbound por SSH e instalar uma instância nova em Debian 13. A instalação foi validada de ponta a ponta em uma VM própria. [Abrir DNS Recursivo](https://rpz.trevizamnetwork.com.br/dns-recursivo)

## Fluxo para o operador

1. Em **DNS Recursivo → Novo servidor**, informe o nome da ficha, o hostname de referência, IPv4 ou IPv6 de gerenciamento, o endereço que será usado para SSH, a porta, o usuário e a senha. O cliente RPZ é opcional. O hostname digitado identifica a ficha e o diagnóstico exibe o hostname que o servidor retorna.
2. Salve o cadastro e abra **Consultar chaves SSH**. O painel lista as impressões digitais apresentadas naquela porta antes de enviar qualquer credencial. Compare com a chave obtida no console do servidor. Para ED25519, use `ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub`. A ficha aceita tanto o valor `SHA256:…` quanto a linha inteira do comando.
3. Salve a impressão digital e clique em **Conectar e diagnosticar**. O diagnóstico é somente leitura. Ele mostra sistema, hostname, CPU, RAM, interfaces, estado do Unbound e sockets na porta 53. Para Debian 13, apresenta threads e caches recomendados com base no perfil do instalador original.
4. Se o diagnóstico indicar Debian 13, ausência de Unbound e porta 53 livre, abra **Revisar instalação**. Ajuste threads, cache de mensagens e cache de registros. O painel mostra a configuração exata antes de confirmar. Na versão atual, o SSH usa autenticação por senha e a instalação exige root ou sudo sem senha.
5. Após confirmar, o painel revalida as condições no host, instala os pacotes, inicializa a âncora raiz DNSSEC, grava o fragmento gerenciado, valida com `unbound-checkconf`, reinicia o serviço e testa uma consulta em `127.0.0.1`. O diagnóstico da ficha é atualizado após o sucesso.

O inventário usa a tabela `recursive_dns_servers`, com senha criptografada e vínculo opcional a `empresas`. Esse vínculo identifica quem opera o servidor. A publicação de listas e zonas continua no RPZ. O módulo exige login com perfil de administrador.

## Instalação assistida

- A prévia vence em dez minutos, fica vinculada ao cadastro e ao perfil aprovado e é consumida na confirmação. Threads são limitadas à CPU detectada e a soma dos dois caches é limitada à metade da RAM detectada. O host confere esses limites novamente antes de instalar.
- O instalador exige Debian 13 com systemd e verifica pacote, configuração existente e uso da porta 53. Uma trava no servidor impede duas instalações simultâneas. Uma instalação do Unbound já presente segue para adoção em uma etapa futura.
- A configuração inicial escuta em `127.0.0.1` e `::1`. O fragmento é `/etc/unbound/unbound.conf.d/55-dns-recursivo.conf`. Se a validação ou o teste falhar após gravar o fragmento, o instalador remove o ajuste e tenta reiniciar o serviço com a configuração do pacote. Em caso de interrupção do APT, confira o estado dos pacotes antes de repetir.
- O pacote Debian pode alterar a resolução local quando encontra uma integração ativa com `resolvconf`. O instalador bloqueia esse caso e aceita o atalho `resolvconf` que aponta para `resolvectl`, como faz o auxiliar do pacote Debian.

## Publicação

| Item | Estado verificado |
| --- | --- |
| Painel | HTTPS com `/login` e `/up` respondendo HTTP 200. A ficha exige sessão administrativa. |
| Código | Branch `feature/dns-recursivo-staging`. Revisão funcional publicada `5dc46f9`. |
| Imagem ativa | `dns-panel-rpz-app:dns-recursivo-5dc46f9`, com serviço web saudável. |
| Banco | Migração `2026_11_27_230000_create_recursive_dns_servers` aplicada no lote 47. |
| Backup anterior à migração | SQLite verificado com `PRAGMA quick_check`. Arquivo `/backups/pre-dns-recursivo-20261127T231239Z.sqlite` no volume do painel. |
| Atualização | Imagens derivadas da imagem web ativa, com overrides em `/root/dns-recursivo-overlay`. O último override do app é `compose.dns-recursivo-askpass.yml`. |

Para operações futuras, confira os arquivos Compose associados ao container antes de recriar o serviço. As imagens anteriores foram preservadas para retorno do app. A cópia de trabalho fica em `/root/dns-recursivo-staging`. Arquivos de entrega anteriores foram guardados em `/root/dns-recursivo-entrega`. A cópia separada em `/opt/dns-recursivo` ficou pendente porque a sessão tinha acesso somente de leitura a esse diretório.

## Validação no laboratório

A ficha **Laboratório Unbound — Debian 13** fica no inventário de DNS Recursivo com ID 2 e sem vínculo com cliente. O SSH da VM passa por um encaminhamento interno do painel. [Abrir a ficha do laboratório](https://rpz.trevizamnetwork.com.br/dns-recursivo/2/edit)

- Um teste HTTP autenticado percorreu cadastro sem chave, consulta das chaves, confirmação da impressão digital, diagnóstico, prévia e instalação via SSH em uma VM Debian 13 com systemd.
- O Unbound ficou ativo, respondeu à consulta DNS pelo loopback e carregou o perfil escolhido de **2 threads, 64 MiB de cache de mensagens e 128 MiB de cache de registros**.
- A ficha no painel de produção foi atualizada pelo diagnóstico após a correção de autenticação. Ela mostra Debian 13 e Unbound ativo.
- A suíte `tests/Feature/RecursiveDnsServersTest.php` passou com **4 testes e 35 asserções**, cobrindo cadastro, proteção da senha, acesso administrativo e confirmação de instalação de uso único.

## Correções encontradas durante os testes

| Sintoma | Correção |
| --- | --- |
| Debian 13 detectado sem perfil recomendado | Leitura separada de `ID` e `VERSION_ID` em `/etc/os-release`, independentemente da ordem. |
| Formulário antigo pedia impressão digital para salvar | Campo retirado da tela **Novo servidor**. A confirmação fica na ficha depois de salvar. Uma aba que ainda mostra o campo no cadastro precisa ser recarregada. |
| Chave consultada e ausente em uma consulta imediatamente seguinte | Reutilização por 15 minutos das chaves coletadas para o mesmo IP e porta. O SSH continua exigindo a chave que corresponde à impressão digital confirmada. |
| Autenticação por senha funcionava no laboratório e falhava em produção | A produção monta `/tmp` com `noexec`. O auxiliar de senha agora é executado a partir da imagem do painel, e os dados temporários continuam em `/tmp`. O diagnóstico foi validado com a proteção ativa. |
| APT expirava ou interferia na entrada do script | Janela de execução ajustada, mensagem de tempo limite e entrada do script isolada da entrada do APT. |
| Primeiro `unbound-checkconf` falhava por falta da chave raiz | Instalação explícita de `dns-root-data` e inicialização com o auxiliar Debian antes da validação. |

## DNS-03 da Speed Fiber

O servidor já estava cadastrado em DNS Recursivo com ID 1 e foi vinculado à empresa Speed Fiber, ID 11. A impressão digital observada para o endereço e a porta cadastrados foi salva após a conferência da identidade do endpoint no inventário RPZ. O cadastro RPZ correspondente é o servidor 15. O hostname exibido no terminal era `DNS-01`, embora o endereço correspondesse ao cadastro **DNS-03** da Speed Fiber. [Identificação e histórico do DNS-03](retomada-2026-10-23-unbound-rpz.txt)

O TCP/48220 inicialmente expirava a partir do painel. A consulta das chaves SSH passou depois da revisão do firewall. As tentativas de diagnóstico seguintes falharam antes da correção de `noexec` em produção. O DNS-03 ainda aguarda um novo teste com o auxiliar corrigido. A instalação e o ajuste de configuração foram testados somente na VM própria.

## Referências e próximas etapas

- O instalador original está em `/root/dns-unbound-setup-v2.11.15.sh`. Ele serviu de referência para o perfil de hardware, Hyperlocal, nftables, Zabbix Agent 2 e FRRouting com OSPF. Para RPZ, habilita `respip` e prepara o fragmento manual `70-rpz.conf`.
- A referência da Telic foi arquivada em `/root/telic-ubnd-referencia`, com índice, nove arquivos e manifesto SHA256. O `file8` testa a resolução e derruba o ExaBGP se a falha persistir após reiniciar o Unbound. O `file6` contém os comandos de anúncio e retirada. Para a futura política Anycast, a proposta discutida foi usar FRR com OSPF ou BGP e retirar só a rota de serviço afetada quando o teste DNS falhar. [Arquivos originais da Telic](https://repo.telic.net.br/files/ubnd/)
- Ficam pendentes a adoção de Unbound existente com prévia e restauração, ajustes após instalação, autenticação SSH por chave privada, ACLs para redes de clientes, Hyperlocal, firewall, Zabbix, Anycast e a geração e aplicação de zonas para clientes RPZ vinculados.
