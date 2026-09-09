<?php

namespace App\Services\Rbl;

use RuntimeException;
use Throwable;

/** DNS A over UDP, using the first system resolver, without shell or global ini changes. */
class DnsblResolver
{
    public static function validName(string $name): bool
    {
        return strlen($name) <= 253 && (bool) preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+\z/iD', $name);
    }

    public function queryFor(string $ip, string $zone): string
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || ! self::validName($zone)) {
            throw new RuntimeException('Alvo IPv4 ou zona DNSBL inválidos.');
        }
        $query = implode('.', array_reverse(explode('.', $ip))).'.'.$zone;
        if (strlen($query) > 253) {
            throw new RuntimeException('Nome da consulta DNSBL excede o limite DNS.');
        }

        return $query;
    }

    public function resolve(string $query, float $timeout): array
    {
        try {
            if (! self::validName($query)) {
                throw new RuntimeException('Consulta DNS inválida.');
            }
            $id = random_int(0, 65535);
            $question = '';
            foreach (explode('.', $query) as $label) {
                $question .= chr(strlen($label)).$label;
            }
            $question .= "\0".pack('nn', 1, 1);
            $packet = pack('nnnnnn', $id, 0x0100, 1, 0, 0, 0).$question;
            $reply = $this->exchange($packet, max(0.001, min(5, $timeout)));

            return $this->parseResponse($reply, $id, $query);
        } catch (DnsblTimeoutException) {
            return ['status' => 'timeout', 'error_message' => 'Tempo limite da consulta DNS excedido.'];
        } catch (Throwable) {
            return ['status' => 'error', 'error_message' => 'Falha na consulta DNS ou resposta inválida.'];
        }
    }

    protected function exchange(string $packet, float $timeout): string
    {
        $config = @file_get_contents('/etc/resolv.conf');
        preg_match('/^nameserver\s+([^\s#]+)/m', $config ?: '', $matches);
        $server = $matches[1] ?? '';
        if (! filter_var($server, FILTER_VALIDATE_IP)) {
            throw new RuntimeException('Resolver DNS indisponível.');
        }
        $address = str_contains($server, ':') ? "[$server]" : $server;
        $socket = @stream_socket_client("udp://{$address}:53", $errno, $error, $timeout);
        if ($socket === false) {
            throw new RuntimeException('Falha de transporte DNS.');
        }
        try {
            $seconds = (int) $timeout;
            stream_set_timeout($socket, $seconds, (int) (($timeout - $seconds) * 1000000));
            if (@fwrite($socket, $packet) !== strlen($packet)) {
                throw new RuntimeException('Falha ao enviar consulta DNS.');
            }
            $reply = @fread($socket, 65535);
            if (stream_get_meta_data($socket)['timed_out']) {
                throw new DnsblTimeoutException;
            }
            if ($reply === false || $reply === '') {
                throw new RuntimeException('Resposta DNS vazia.');
            }

            return $reply;
        } finally {
            fclose($socket);
        }
    }

    /** Validate transaction, question, compression and bounds before interpreting answers. */
    public function parseResponse(string $packet, int $id, string $query): array
    {
        if (strlen($packet) < 12) {
            throw new RuntimeException('Cabeçalho DNS incompleto.');
        }
        $h = unpack('nid/nflags/nquestions/nanswers/nauthority/nadditional', substr($packet, 0, 12));
        if ($h['id'] !== $id || ! ($h['flags'] & 0x8000) || ($h['flags'] & 0x7A00) || $h['questions'] !== 1) {
            throw new RuntimeException('Cabeçalho DNS inválido ou truncado.');
        }
        $offset = 12;
        $name = $this->readName($packet, $offset);
        if (strtolower($name) !== strtolower($query) || substr($packet, $offset, 4) !== pack('nn', 1, 1)) {
            throw new RuntimeException('Pergunta DNS divergente.');
        }
        $offset += 4;
        $rcode = $h['flags'] & 15;
        if ($rcode === 3) {
            return ['status' => 'clean'];
        }
        if ($rcode !== 0) {
            return ['status' => 'error', 'error_message' => 'Servidor DNS retornou erro (RCODE '.$rcode.').'];
        }
        $addresses = [];
        $aliases = [strtolower($query)];
        for ($i = 0; $i < $h['answers']; $i++) {
            $owner = strtolower($this->readName($packet, $offset));
            if ($offset + 10 > strlen($packet)) {
                throw new RuntimeException('Registro DNS incompleto.');
            }
            $rr = unpack('ntype/nclass/Nttl/nlength', substr($packet, $offset, 10));
            $offset += 10;
            if ($offset + $rr['length'] > strlen($packet)) {
                throw new RuntimeException('Dados DNS incompletos.');
            }
            if ($rr['type'] === 1 && ($rr['length'] !== 4 || $rr['class'] !== 1 || ! in_array($owner, $aliases, true))) {
                throw new RuntimeException('Registro A inválido ou não relacionado à consulta.');
            }
            if ($rr['class'] === 1 && in_array($owner, $aliases, true)) {
                if ($rr['type'] === 1 && $rr['length'] === 4) {
                    $addresses[] = inet_ntop(substr($packet, $offset, 4));
                } elseif ($rr['type'] === 5) {
                    $position = $offset;
                    $aliases[] = strtolower($this->readName($packet, $position));
                    if ($position !== $offset + $rr['length']) {
                        throw new RuntimeException('Alias DNS inválido.');
                    }
                }
            }
            $offset += $rr['length'];
        }
        $response = implode(', ', array_unique($addresses));
        // Provider access errors and unexpected A records must never imply a clean target.
        foreach ($addresses as $address) {
            if (! str_starts_with($address, '127.0.0.')) {
                return ['status' => 'error', 'response' => $response, 'error_message' => 'Resposta fora de 127.0.0.x; verifique as condições de acesso da RBL.'];
            }
        }
        if ($addresses) {
            return ['status' => 'listed', 'response' => $response];
        }
        if (count($aliases) > 1) {
            return ['status' => 'error', 'error_message' => 'Alias DNS sem resposta IPv4 conclusiva.'];
        }

        return ['status' => 'clean'];
    }

    private function readName(string $packet, int &$offset): string
    {
        $position = $offset;
        $end = null;
        $labels = [];
        $seen = [];
        while (true) {
            if ($position >= strlen($packet) || isset($seen[$position])) {
                throw new RuntimeException('Nome DNS inválido.');
            }
            $seen[$position] = true;
            $length = ord($packet[$position++]);
            if (($length & 0xC0) === 0xC0) {
                if ($position >= strlen($packet)) {
                    throw new RuntimeException('Ponteiro DNS inválido.');
                }
                $end ??= $position + 1;
                $position = (($length & 63) << 8) | ord($packet[$position]);

                continue;
            }
            if ($length > 63 || $position + $length > strlen($packet)) {
                throw new RuntimeException('Rótulo DNS inválido.');
            }
            if ($length === 0) {
                $offset = $end ?? $position;

                return implode('.', $labels);
            }
            $labels[] = substr($packet, $position, $length);
            if (strlen(implode('.', $labels)) > 253) {
                throw new RuntimeException('Nome DNS longo demais.');
            }
            $position += $length;
        }
    }
}
