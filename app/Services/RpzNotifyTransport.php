<?php

namespace App\Services;

/** Bounded UDP NOTIFY transaction. An ACK is receipt, not proof of zone installation. */
class RpzNotifyTransport
{
    /** @return array{status:string,attempts:int,error:?string} */
    public function send(string $ip, string $zoneName, int $port = 53): array
    {
        $socket = @socket_create(str_contains($ip, ':') ? AF_INET6 : AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($socket === false) {
            return ['status' => 'failed', 'attempts' => 0, 'error' => socket_strerror(socket_last_error())];
        }
        try {
            // Connected UDP restricts incoming datagrams to this IP and port.
            if (! @socket_connect($socket, $ip, $port)) {
                return ['status' => 'failed', 'attempts' => 0, 'error' => socket_strerror(socket_last_error($socket))];
            }
            $id = random_int(0, 65535);
            $packet = pack('nnnnnn', $id, 0x2400, 1, 0, 0, 0)
                .RpzDnsMessage::encodeName($zoneName).pack('nn', 6, 1);
            $attempts = max(1, min(5, (int) config('rpz.notify_attempts', 3)));
            $timeout = max(0.05, min(5.0, (float) config('rpz.notify_timeout', 1)));
            for ($attempt = 1; $attempt <= $attempts; $attempt++) {
                if (@socket_send($socket, $packet, strlen($packet), 0) !== strlen($packet)) {
                    return ['status' => 'failed', 'attempts' => $attempt, 'error' => socket_strerror(socket_last_error($socket))];
                }
                $deadline = hrtime(true) + (int) (min(5, $timeout * (2 ** ($attempt - 1))) * 1e9);
                while (($remaining = ($deadline - hrtime(true)) / 1e9) > 0) {
                    $read = [$socket];
                    $write = $except = [];
                    $seconds = (int) $remaining;
                    $ready = @socket_select($read, $write, $except, $seconds, (int) (($remaining - $seconds) * 1e6));
                    if ($ready === false) {
                        return ['status' => 'failed', 'attempts' => $attempt, 'error' => socket_strerror(socket_last_error($socket))];
                    }
                    if ($ready === 0) {
                        break;
                    }
                    $received = @socket_recv($socket, $reply, 4096, 0);
                    if ($received === false) {
                        return ['status' => 'failed', 'attempts' => $attempt, 'error' => socket_strerror(socket_last_error($socket))];
                    }
                    $ack = RpzDnsMessage::question($reply);
                    if ($ack === null || $ack['id'] !== $id || ($ack['flags'] & 0xF800) !== 0xA000
                        || ($ack['flags'] & 0x0200) !== 0 || $ack['qtype'] !== 6 || $ack['qclass'] !== 1
                        || strcasecmp($ack['qname'], rtrim($zoneName, '.')) !== 0) {
                        continue;
                    }
                    $rcode = $ack['flags'] & 0x000F;

                    return [
                        'status' => $rcode === 0 ? 'acknowledged' : 'rejected',
                        'attempts' => $attempt,
                        'error' => $rcode === 0 ? null : 'DNS RCODE '.$rcode,
                    ];
                }
            }

            return ['status' => 'unconfirmed', 'attempts' => $attempts, 'error' => 'No matching NOTIFY response'];
        } finally {
            socket_close($socket);
        }
    }
}
