<?php

namespace App\Services;

use App\Models\RecursiveDnsServer;
use RuntimeException;
use Symfony\Component\Process\Process;

class RecursiveDnsDiagnostic
{
    public function run(RecursiveDnsServer $server): array
    {
        $address = $server->sshAddress();
        $scan = new Process(['ssh-keyscan', '-T', '4', '-p', (string) $server->ssh_port, '--', $address]);
        $scan->setTimeout(6)->run();

        $hostKey = null;
        foreach (explode("\n", $scan->getOutput()) as $line) {
            $fields = preg_split('/\s+/', trim($line));
            if (count($fields) !== 3 || str_starts_with($fields[0], '#')) {
                continue;
            }
            $key = base64_decode($fields[2], true);
            if ($key !== false && hash_equals($server->ssh_fingerprint, 'SHA256:'.rtrim(base64_encode(hash('sha256', $key, true)), '='))) {
                $hostKey = ($server->ssh_port == 22 ? $address : "[$address]:{$server->ssh_port}").' '.$fields[1].' '.$fields[2]."\n";
                break;
            }
        }

        if ($hostKey === null) {
            throw new RuntimeException('Servidor inacessível ou impressão digital SSH divergente.');
        }

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
                '-o', 'GlobalKnownHostsFile=/dev/null', '-o', 'ConnectTimeout=5',
                '-o', 'PreferredAuthentications=password', '-o', 'NumberOfPasswordPrompts=1',
                '--', $address, 'sh -s',
            ], null, ['SSH_ASKPASS' => "$directory/askpass", 'SSH_ASKPASS_REQUIRE' => 'force']);
            $process->setTimeout(12)->setInput(<<<'SH'
                export LC_ALL=C
                printf 'hostname=%s\n' "$(hostname)"
                printf 'system=%s\n' "$(sed -n 's/^PRETTY_NAME=//p' /etc/os-release | tr -d '"')"
                os_id="$(sed -n 's/^ID=//p' /etc/os-release | tr -d '"')"
                os_version="$(sed -n 's/^VERSION_ID=//p' /etc/os-release | tr -d '"')"
                printf 'platform=%s:%s\n' "$os_id" "$os_version"
                printf 'cpu=%s\n' "$(getconf _NPROCESSORS_ONLN)"
                printf 'ram_mib=%s\n' "$(awk '/^MemTotal:/ {print int($2/1024)}' /proc/meminfo)"
                command -v unbound >/dev/null 2>&1 || [ -x /usr/sbin/unbound ]
                printf 'unbound=%s\n' "$?"
                systemctl is-active --quiet unbound 2>/dev/null
                printf 'active=%s\n' "$?"
                if command -v ip >/dev/null 2>&1; then
                    printf 'interfaces=%s\n' "$(ip -o link show | awk -F': ' '{print $2}' | paste -sd,)"
                fi
                if command -v ss >/dev/null 2>&1; then
                    printf 'port_53=%s\n' "$(ss -H -lntu 'sport = :53' | wc -l)"
                fi
                SH)->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('Falha na conexão, autenticação ou execução do diagnóstico SSH.');
            }

            $result = [];
            foreach (explode("\n", $process->getOutput()) as $line) {
                [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
                if (in_array($key, ['hostname', 'system', 'platform', 'cpu', 'ram_mib', 'unbound', 'active', 'interfaces', 'port_53'], true)) {
                    $result[$key] = mb_strcut(mb_scrub($value, 'UTF-8'), 0, 240, 'UTF-8');
                }
            }
            if (! isset($result['hostname'], $result['unbound'], $result['active'])) {
                throw new RuntimeException('O servidor não retornou um diagnóstico válido.');
            }

            $memory = filter_var($result['ram_mib'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $cpu = filter_var($result['cpu'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (($result['platform'] ?? null) === 'debian:13' && $memory && $cpu) {
                [$messageCache, $recordCache] = match (true) {
                    $memory < 2800 => [128, 256],
                    $memory < 6144 => [256, 1024],
                    $memory < 12288 => [512, 1024],
                    default => [1024, 2048],
                };
                $result['recommended'] = ['threads' => $cpu, 'message_cache' => $messageCache, 'record_cache' => $recordCache];
            }

            return $result;
        } finally {
            foreach (['known_hosts', 'password', 'askpass'] as $file) {
                if (is_file("$directory/$file")) {
                    unlink("$directory/$file");
                }
            }
            rmdir($directory);
        }
    }
}
