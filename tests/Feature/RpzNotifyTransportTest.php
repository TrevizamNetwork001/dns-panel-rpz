<?php

namespace Tests\Feature;

use App\Services\RpzNotifyTransport;
use Tests\TestCase;

class RpzNotifyTransportTest extends TestCase
{
    private function exchangeWithLocalPeer(string $mode): array
    {
        $server = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_bind($server, '127.0.0.1', 0);
        socket_getsockname($server, $ip, $port);
        socket_set_option($server, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 2, 'usec' => 0]);
        $pid = pcntl_fork();
        if ($pid === -1) {
            socket_close($server);
            $this->fail('Cannot fork local UDP peer');
        }
        if ($pid === 0) {
            $count = 0;
            while (@socket_recvfrom($server, $packet, 4096, 0, $source, $sourcePort) !== false) {
                $count++;
                if ($mode === 'retry' && $count === 1) {
                    continue;
                }
                $ack = substr_replace($packet, pack('n', $mode === 'reject' ? 0xA005 : 0xA000), 2, 2);
                if ($mode === 'wrong_id') {
                    $id = unpack('n', substr($packet, 0, 2))[1];
                    $ack = substr_replace($ack, pack('n', ($id + 1) % 65536), 0, 2);
                }
                if ($mode !== 'silent') {
                    socket_sendto($server, $ack, strlen($ack), 0, $source, $sourcePort);
                }
                if (in_array($mode, ['retry', 'reject', 'ack'], true) || $count === 3) {
                    break;
                }
            }
            socket_close($server);
            exit(0);
        }
        try {
            config(['rpz.notify_attempts' => 3, 'rpz.notify_timeout' => 0.05]);

            return app(RpzNotifyTransport::class)->send('127.0.0.1', 'rpz.example', $port);
        } finally {
            // Keep the parent socket open until the transaction finishes (no ICMP shortcut).
            socket_close($server);
            pcntl_waitpid($pid, $status);
        }
    }

    public function test_valid_ack_is_recognized(): void
    {
        $this->assertSame(['status' => 'acknowledged', 'attempts' => 1, 'error' => null], $this->exchangeWithLocalPeer('ack'));
    }

    public function test_lost_first_datagram_is_retried(): void
    {
        $this->assertSame(['status' => 'acknowledged', 'attempts' => 2, 'error' => null], $this->exchangeWithLocalPeer('retry'));
    }

    public function test_mismatched_ack_never_counts_as_receipt(): void
    {
        $result = $this->exchangeWithLocalPeer('wrong_id');
        $this->assertSame('unconfirmed', $result['status']);
        $this->assertSame(3, $result['attempts']);
    }

    public function test_silence_is_bounded_and_not_reported_as_success(): void
    {
        $result = $this->exchangeWithLocalPeer('silent');
        $this->assertSame('unconfirmed', $result['status']);
        $this->assertSame(3, $result['attempts']);
    }

    public function test_refused_response_is_distinct_from_ack(): void
    {
        $this->assertSame('rejected', $this->exchangeWithLocalPeer('reject')['status']);
    }
}
