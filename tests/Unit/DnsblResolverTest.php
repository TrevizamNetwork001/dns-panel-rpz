<?php

namespace Tests\Unit;

use App\Services\Rbl\DnsblResolver;
use App\Services\Rbl\DnsblTimeoutException;
use PHPUnit\Framework\TestCase;

class DnsblResolverTest extends TestCase
{
    private function resolver(int $rcode = 0, array $addresses = [], ?string $failure = null): DnsblResolver
    {
        return new class($rcode, $addresses, $failure) extends DnsblResolver
        {
            public function __construct(private int $rcode, private array $addresses, private ?string $failure) {}

            protected function exchange(string $packet, float $timeout): string
            {
                if ($this->failure === 'timeout') {
                    throw new DnsblTimeoutException;
                }
                if ($this->failure === 'error') {
                    throw new \RuntimeException('Internal secret');
                }
                if ($this->failure === 'malformed') {
                    return 'bad';
                }
                $id = unpack('n', substr($packet, 0, 2))[1];
                $response = pack('nnnnnn', $id, 0x8180 | $this->rcode, 1, count($this->addresses), 0, 0).substr($packet, 12);
                foreach ($this->addresses as $address) {
                    $response .= "\xc0\x0c".pack('nnNn', 1, 1, 60, 4).inet_pton($address);
                }

                return $response;
            }
        };
    }

    public function test_query_reverses_ipv4(): void
    {
        $this->assertSame('4.3.2.1.zen.spamhaus.org', (new DnsblResolver)->queryFor('1.2.3.4', 'zen.spamhaus.org'));
    }

    public function test_loopback_answers_are_listed(): void
    {
        $result = $this->resolver(0, ['127.0.0.2', '127.0.0.4'])->resolve('4.3.2.1.zen.spamhaus.org', 1);
        $this->assertSame('listed', $result['status']);
        $this->assertSame('127.0.0.2, 127.0.0.4', $result['response']);
    }

    public function test_nxdomain_and_nodata_are_clean(): void
    {
        foreach ([0, 3] as $rcode) {
            $this->assertSame('clean', $this->resolver($rcode)->resolve('4.3.2.1.example.org', 1)['status']);
        }
    }

    public function test_dns_failures_are_controlled(): void
    {
        foreach (['timeout' => 'timeout', 'error' => 'error', 'malformed' => 'error'] as $failure => $status) {
            $result = $this->resolver(0, [], $failure)->resolve('4.3.2.1.example.org', 1);
            $this->assertSame($status, $result['status']);
            $this->assertStringNotContainsString('Internal secret', $result['error_message']);
        }
        foreach ([2, 5] as $rcode) {
            $this->assertSame('error', $this->resolver($rcode)->resolve('4.3.2.1.example.org', 1)['status']);
        }
    }

    public function test_provider_access_errors_are_not_blacklist_events_or_clean_results(): void
    {
        foreach (['127.255.255.254', '192.0.2.1'] as $address) {
            $this->assertSame('error', $this->resolver(0, [$address])->resolve('4.3.2.1.example.org', 1)['status']);
        }
    }

    public function test_rejects_invalid_queries_without_dns(): void
    {
        $this->assertSame('error', (new DnsblResolver)->resolve('https://invalid/zone', 1)['status']);
        $this->expectException(\RuntimeException::class);
        (new DnsblResolver)->queryFor('::1', 'example.org');
    }

    public function test_rejects_mismatched_and_truncated_packets(): void
    {
        $resolver = new DnsblResolver;
        foreach ([0x8380, 0x0180, 0x8980] as $flags) {
            try {
                $resolver->parseResponse(pack('nnnnnn', 12, $flags, 1, 0, 0, 0), 12, 'example.org');
                $this->fail('Invalid flags accepted');
            } catch (\RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->expectException(\RuntimeException::class);
        $resolver->parseResponse(pack('nnnnnn', 13, 0x8180, 1, 0, 0, 0), 12, 'example.org');
    }

    public function test_compression_cycles_are_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        (new DnsblResolver)->parseResponse(pack('nnnnnn', 12, 0x8180, 1, 0, 0, 0)."\xc0\x0c", 12, 'example.org');
    }
}
