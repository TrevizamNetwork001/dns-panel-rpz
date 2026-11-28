<?php

namespace App\Services;

use App\Models\RecursiveDnsServer;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Symfony\Component\Process\Process;

class RecursiveDnsSsh
{
    public function execute(RecursiveDnsServer $server, string $command, string $input, int $timeout): Process
    {
        if ($server->ssh_fingerprint === '') {
            throw new RuntimeException('Confirme a impressão digital do servidor antes de conectar com a senha.');
        }

        $address = $server->sshAddress();
        $cacheKey = $this->hostKeyCacheKey($server);
        $keys = Cache::get($cacheKey, []);
        if (! isset($keys[$server->ssh_fingerprint])) {
            $keys = $this->hostKeys($server);
            Cache::put($cacheKey, $keys, now()->addMinutes(15));
        }
        $key = $keys[$server->ssh_fingerprint] ?? null;
        if ($key === null) {
            throw new RuntimeException('A impressão digital confirmada não apareceu entre as chaves apresentadas pelo servidor. Confira o IP, a porta e a chave pela console.');
        }
        $host = $server->ssh_port == 22 ? $address : "[$address]:{$server->ssh_port}";
        $hostKey = $host.' '.$key."\n";

        $directory = sys_get_temp_dir().'/recursive-dns-'.bin2hex(random_bytes(16));
        if (! mkdir($directory, 0700)) {
            throw new RuntimeException('Não foi possível preparar a conexão SSH.');
        }

        try {
            file_put_contents("$directory/known_hosts", $hostKey);
            file_put_contents("$directory/password", $server->ssh_password);
            file_put_contents("$directory/askpass", "#!/bin/sh\nexec cat -- \"\$(dirname -- \"\$0\")/password\"\n");
            chmod("$directory/askpass", 0700);

            $process = new Process([
                'ssh', '-F', '/dev/null', '-T', '-p', (string) $server->ssh_port, '-l', $server->ssh_user,
                '-o', 'StrictHostKeyChecking=yes', '-o', "UserKnownHostsFile=$directory/known_hosts",
                '-o', 'GlobalKnownHostsFile=/dev/null', '-o', 'ConnectTimeout=8',
                '-o', 'PreferredAuthentications=password', '-o', 'NumberOfPasswordPrompts=1',
                '--', $address, $command,
            ], null, ['SSH_ASKPASS' => "$directory/askpass", 'SSH_ASKPASS_REQUIRE' => 'force']);
            $process->setTimeout($timeout)->setInput($input)->run();

            return $process;
        } finally {
            foreach (['known_hosts', 'password', 'askpass'] as $file) {
                if (is_file("$directory/$file")) {
                    unlink("$directory/$file");
                }
            }
            rmdir($directory);
        }
    }

    public function fingerprints(RecursiveDnsServer $server): array
    {
        $keys = $this->hostKeys($server);
        Cache::put($this->hostKeyCacheKey($server), $keys, now()->addMinutes(15));

        return array_keys($keys);
    }

    private function hostKeyCacheKey(RecursiveDnsServer $server): string
    {
        return 'dns-recursivo:ssh-host-keys:'.hash('sha256', $server->sshAddress().'|'.$server->ssh_port);
    }

    private function hostKeys(RecursiveDnsServer $server): array
    {
        $scan = new Process(['ssh-keyscan', '-T', '8', '-p', (string) $server->ssh_port, '--', $server->sshAddress()]);
        $scan->setTimeout(10)->run();

        $keys = [];
        foreach (explode("\n", $scan->getOutput()) as $line) {
            $fields = preg_split('/\s+/', trim($line));
            if (count($fields) !== 3 || str_starts_with($fields[0], '#')) {
                continue;
            }

            $key = base64_decode($fields[2], true);
            if ($key !== false && $key !== '') {
                $fingerprint = 'SHA256:'.rtrim(base64_encode(hash('sha256', $key, true)), '=');
                $keys[$fingerprint] = $fields[1].' '.$fields[2];
            }
        }

        if ($keys === []) {
            throw new RuntimeException('O painel não recebeu uma chave SSH. Confira o IP, a porta e se o firewall permite acesso a partir do painel.');
        }

        return $keys;
    }
}
