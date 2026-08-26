<?php
namespace Tests\Unit;
use App\Services\DomainNormalizer;
use PHPUnit\Framework\TestCase;
class DomainNormalizerTest extends TestCase
{
 public function test_normalizes_urls_case_and_trailing_dot():void{$n=new DomainNormalizer();$this->assertSame('example.com',$n->normalize(' HTTPS://Example.COM/path '));$this->assertSame('example.com',$n->normalize('Example.COM.'));}
 public function test_rejects_ips_and_pdf_garbage():void{$n=new DomainNormalizer();$this->assertNull($n->normalize('192.0.2.1'));$this->assertNull($n->normalize('texto sem dominio'));}
}
