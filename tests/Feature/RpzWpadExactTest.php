<?php
namespace Tests\Feature;
use App\Models\Dominio;
use App\Models\Lista;
use App\Services\RpzZoneBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class RpzWpadExactTest extends TestCase
{
    use RefreshDatabase;
    public function test_wpad_rule_is_exact_and_other_domains_keep_subdomain_protection(): void
    {
        $list = Lista::factory()->create();
        foreach (['wpad.domain.name', 'malware.example'] as $name) {
            Dominio::factory()->for($list)->create(['dominio' => $name]);
        }
        $builder = app(RpzZoneBuilder::class);
        $zone = $builder->build($builder->listQuery($list));
        $this->assertStringContainsString("\nwpad.domain.name CNAME .\n", $zone);
        $this->assertStringNotContainsString('*.wpad.domain.name', $zone);
        $this->assertStringNotContainsString("\nwpad CNAME", $zone);
        $this->assertStringNotContainsString("\ndomain.name CNAME", $zone);
        $this->assertStringContainsString("\n*.malware.example CNAME .\n", $zone);
    }
}
