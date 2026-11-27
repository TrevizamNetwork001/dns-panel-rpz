<?php

namespace App\Services;

use App\Models\RecursiveDnsServer;
use RuntimeException;
use Symfony\Component\Process\Process;

class RecursiveDnsSsh
{
    public function execute(RecursiveDnsServer $server, string $command, string $input, int $timeout): Process
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
}
