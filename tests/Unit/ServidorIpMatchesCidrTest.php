<?php

namespace Tests\Unit;

use App\Models\Servidor;
use Tests\TestCase;

class ServidorIpMatchesCidrTest extends TestCase
{
    public function test_exact_ip_match(): void
    {
        $this->assertTrue(Servidor::ipMatchesCidr('203.0.113.5', '203.0.113.5'));
        $this->assertFalse(Servidor::ipMatchesCidr('203.0.113.6', '203.0.113.5'));
    }

    public function test_ipv4_cidr_match(): void
    {
        $this->assertTrue(Servidor::ipMatchesCidr('203.0.113.42', '203.0.113.0/24'));
        $this->assertFalse(Servidor::ipMatchesCidr('203.0.114.42', '203.0.113.0/24'));
    }

    public function test_ipv4_cidr_boundary(): void
    {
        $this->assertTrue(Servidor::ipMatchesCidr('10.0.0.1', '10.0.0.0/30'));
        $this->assertTrue(Servidor::ipMatchesCidr('10.0.0.2', '10.0.0.0/30'));
        $this->assertFalse(Servidor::ipMatchesCidr('10.0.0.5', '10.0.0.0/30'));
    }

    public function test_ipv6_cidr_match(): void
    {
        $this->assertTrue(Servidor::ipMatchesCidr('2001:db8::1', '2001:db8::/32'));
        $this->assertFalse(Servidor::ipMatchesCidr('2001:db9::1', '2001:db8::/32'));
    }

    public function test_invalid_cidr_returns_false(): void
    {
        $this->assertFalse(Servidor::ipMatchesCidr('203.0.113.5', ''));
        $this->assertFalse(Servidor::ipMatchesCidr('203.0.113.5', 'nao-e-um-ip/24'));
    }

    public function test_ipv4_and_ipv6_do_not_cross_match(): void
    {
        $this->assertFalse(Servidor::ipMatchesCidr('2001:db8::1', '203.0.113.0/24'));
        $this->assertFalse(Servidor::ipMatchesCidr('203.0.113.5', '2001:db8::/32'));
    }
}
