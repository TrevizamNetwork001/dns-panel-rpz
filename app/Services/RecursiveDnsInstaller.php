<?php

namespace App\Services;

use App\Models\RecursiveDnsServer;
use RuntimeException;

class RecursiveDnsInstaller
{
    public function __construct(private RecursiveDnsSsh $ssh) {}

    public function configuration(array $profile): string
    {
        return sprintf(
            "server:\n    interface: 127.0.0.1\n    interface: ::1\n    num-threads: %d\n    msg-cache-size: %dm\n    rrset-cache-size: %dm\n",
            $profile['threads'], $profile['message_cache'], $profile['record_cache'],
        );
    }

    public function install(RecursiveDnsServer $server, array $profile): void
    {
        $prefix = $server->ssh_user === 'root' ? '' : 'sudo -n ';
        $command = sprintf(
            '%stimeout -k 5s 83s sh -s -- %d %d %d',
            $prefix, $profile['threads'], $profile['message_cache'], $profile['record_cache'],
        );
        $script = file_get_contents(resource_path('scripts/install-recursive-unbound.sh'));
        $input = str_replace('__UNBOUND_CONFIGURATION__', rtrim($this->configuration($profile), "\n"), $script);
        $process = $this->ssh->execute($server, $command, $input, 90);
        preg_match_all('/^unbound-install:([a-z]+)$/m', $process->getOutput(), $status);
        $result = end($status[1]);

        if ($process->isSuccessful() && $result === 'ok') {
            return;
        }

        throw new RuntimeException(match ($result) {
            'busy' => 'Há outra instalação em execução nesse servidor.',
            'platform' => 'A instalação inicial requer Debian 13.',
            'existing' => 'Já existe Unbound ou configuração de Unbound no servidor. Use o fluxo de adoção quando estiver disponível.',
            'systemd' => 'O servidor precisa estar executando systemd.',
            'sockets' => 'Não foi possível verificar o uso da porta DNS.',
            'port' => 'A porta 53 já está em uso no servidor.',
            'resolvconf' => 'Este servidor usa resolvconf. Revise a integração do pacote antes de instalar o Unbound.',
            'resources' => 'CPU ou RAM do servidor não comportam o perfil aprovado.',
            'packages' => 'O APT falhou. Verifique os repositórios e o estado dos pacotes antes de tentar novamente.',
            'trust' => 'O pacote foi instalado, mas a chave raiz de DNSSEC não pôde ser inicializada.',
            'config', 'service', 'query' => 'A validação falhou. O arquivo de ajuste foi removido. Verifique o estado do pacote e do serviço.',
            'restore' => 'A validação falhou e o serviço não voltou após remover o ajuste. Verifique o Unbound no servidor.',
            default => 'A instalação não foi concluída. Confira a conexão e as permissões de root ou sudo sem senha, e repita o diagnóstico.',
        });
    }
}
