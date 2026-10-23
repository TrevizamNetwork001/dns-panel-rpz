<?php

namespace Tests\Feature;

use App\Models\Lista;
use App\Services\DomainNormalizer;
use App\Services\ExternalListaSyncer;
use App\Services\RpzDirtyMarker;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExternalFeedSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function syncer(array $records): ExternalListaSyncer
    {
        return new class(app(DomainNormalizer::class), app(RpzDirtyMarker::class), $records) extends ExternalListaSyncer
        {
            public function __construct(DomainNormalizer $normalizer, RpzDirtyMarker $dirty, private array $records)
            {
                parent::__construct($normalizer, $dirty);
            }

            protected function feedDnsRecords(string $host): array
            {
                return $this->records;
            }
        };
    }

    public function test_connection_is_pinned_to_validated_dns_addresses(): void
    {
        Queue::fake();
        $list = Lista::factory()->externa('test', 'https://feed.example.net:8443/domains', 'plain')->create();
        $body = implode("\n", array_map(fn ($i) => 'domain'.$i.'.example', range(1, 100)));
        Http::fake(function ($request, $options) use ($body) {
            $this->assertSame(['feed.example.net:8443:8.8.8.8,[2606:4700:4700::1111]'], $options['curl'][CURLOPT_RESOLVE]);
            $this->assertSame('', $options['proxy']);
            $this->assertFalse($options['allow_redirects']);

            return Http::response($body);
        });

        $result = $this->syncer([['ip' => '8.8.8.8'], ['ipv6' => '2606:4700:4700::1111']])->sync($list);

        $this->assertSame('ok', $result['status']);
        $this->assertDatabaseCount('dominios', 100);
    }

    public function test_one_private_dns_answer_rejects_the_entire_feed(): void
    {
        $list = Lista::factory()->externa('test', 'https://feed.example.net/domains', 'plain')->create();
        Http::fake();

        $result = $this->syncer([['ip' => '8.8.8.8'], ['ip' => '127.0.0.1']])->sync($list);

        $this->assertSame('erro', $result['status']);
        Http::assertNothingSent();
        $this->assertDatabaseCount('dominios', 0);
    }

    public function test_private_ipv4_mapped_ipv6_and_multicast_are_rejected(): void
    {
        Http::fake();
        foreach (['http://[::ffff:127.0.0.1]/feed', 'http://224.0.0.1/feed', 'http://100.64.0.1/feed', 'http://[64:ff9b::7f00:1]/feed'] as $url) {
            $list = Lista::factory()->externa('test', $url, 'plain')->create();
            $this->assertSame('erro', $this->syncer([])->sync($list)['status']);
        }
        Http::assertNothingSent();
    }

    public function test_body_transfer_is_aborted_before_exceeding_limit_and_lock_is_released(): void
    {
        $list = Lista::factory()->externa('test', 'https://feed.example.net/domains', 'plain')->create();
        config(['lists.external_feed_max_bytes' => 100]);
        $attempts = 0;
        $aborted = [];
        Http::fake(function ($request, $options) use (&$attempts, &$aborted) {
            $attempts++;
            try {
                if ($attempts === 1) {
                    ($options['progress'])(0, 101);
                } else {
                    ($options['on_headers'])(new Response(200, ['Content-Length' => '101']));
                }
            } catch (\LengthException $e) {
                $aborted[] = $attempts;
                throw $e;
            }

            return Http::response('not aborted');
        });
        $syncer = $this->syncer([['ip' => '8.8.8.8']]);
        $this->assertSame('erro', $syncer->sync($list)['status']);
        $this->assertDatabaseCount('dominios', 0);

        $this->assertSame('erro', $syncer->sync($list)['status']);
        $this->assertSame(2, $attempts); // The second request proves the lock was released.
        $this->assertSame([1, 2], $aborted);
    }

    public function test_fetch_errors_do_not_expose_url_credentials_or_tokens(): void
    {
        $list = Lista::factory()->externa('test', 'https://feed.example.net/domains?key=secret-token', 'plain')->create();
        Http::fake(fn () => throw new \RuntimeException('Network failed: secret-token'));

        $result = $this->syncer([['ip' => '8.8.8.8']])->sync($list);

        $this->assertSame('erro', $result['status']);
        $this->assertStringNotContainsString('secret-token', $result['motivo']);
    }

    public function test_unrequested_compression_cannot_bypass_the_transfer_limit(): void
    {
        $list = Lista::factory()->externa('test', 'HTTPS://FEED.EXAMPLE.NET/domains', 'plain')->create();
        $rejected = false;
        Http::fake(function ($request, $options) use (&$rejected) {
            $this->assertSame(['feed.example.net:443:8.8.8.8'], $options['curl'][CURLOPT_RESOLVE]);
            $this->assertFalse($options['decode_content']);
            $this->assertSame('identity', $request->header('Accept-Encoding')[0]);
            try {
                ($options['on_headers'])(new Response(200, ['Content-Encoding' => 'gzip', 'Content-Length' => '10']));
            } catch (\UnexpectedValueException $e) {
                $rejected = true;
                throw $e;
            }

            return Http::response('not rejected');
        });

        $this->assertSame('erro', $this->syncer([['ip' => '8.8.8.8']])->sync($list)['status']);
        $this->assertTrue($rejected);
        $this->assertDatabaseCount('dominios', 0);
    }
}
