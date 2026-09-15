<?php

namespace Tests\Unit;

use App\Services\DomainNormalizer;
use PHPUnit\Framework\TestCase;

class DomainNormalizerTest extends TestCase
{
    public function test_normalizes_urls_case_and_trailing_dot(): void
    {
        $n = new DomainNormalizer;
        $this->assertSame('example.com', $n->normalize(' HTTPS://Example.COM/path '));
        $this->assertSame('example.com', $n->normalize('Example.COM.'));
    }

    public function test_rejects_ips_and_pdf_garbage(): void
    {
        $n = new DomainNormalizer;
        $this->assertNull($n->normalize('192.0.2.1'));
        $this->assertNull($n->normalize('texto sem dominio'));
    }

    public function test_strips_www_prefix(): void
    {
        $n = new DomainNormalizer;
        $this->assertSame('exemplo.com', $n->normalize('www.exemplo.com'));
        $this->assertSame('exemplo.com', $n->normalize('WWW.Exemplo.COM.'));
        $this->assertSame('exemplo.com', $n->normalize('https://www.exemplo.com/path'));
        // Só remove o "www." do inicio, nao um label "www" no meio do dominio.
        $this->assertSame('sub.www.exemplo.com', $n->normalize('sub.www.exemplo.com'));
    }
}
