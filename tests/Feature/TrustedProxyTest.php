<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    public function test_trusted_internal_proxy_headers_restore_public_client_ip_and_https_url(): void
    {
        Route::get('/_proxy-check', fn () => response()->json([
            'ip' => request()->ip(),
            'secure' => request()->isSecure(),
            'url' => url('/rpz/example.zone'),
            'host' => request()->getHost(),
        ]));

        $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])
            ->withHeaders([
                'X-Forwarded-For' => '203.0.113.77, 127.0.0.1, 172.18.0.5',
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Host' => 'rpz.trevizamnetwork.com.br',
                'X-Forwarded-Port' => '443',
                'Host' => 'rpz.trevizamnetwork.com.br',
            ])
            ->get('/_proxy-check')
            ->assertOk()
            ->assertJson([
                'ip' => '203.0.113.77',
                'secure' => true,
                'url' => 'https://rpz.trevizamnetwork.com.br/rpz/example.zone',
                'host' => 'rpz.trevizamnetwork.com.br',
            ]);
    }

    public function test_untrusted_remote_addr_cannot_spoof_forwarded_client_ip(): void
    {
        Route::get('/_proxy-check-untrusted', fn () => response()->json([
            'ip' => request()->ip(),
        ]));

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->withHeaders([
                'X-Forwarded-For' => '203.0.113.77',
                'X-Forwarded-Proto' => 'https',
            ])
            ->get('/_proxy-check-untrusted')
            ->assertOk()
            ->assertJson([
                'ip' => '198.51.100.10',
            ]);
    }
}
