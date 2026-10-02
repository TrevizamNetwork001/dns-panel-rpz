<?php

namespace Tests\Unit;

use App\Models\Servidor;
use Tests\TestCase;

class ServidorUnboundVersionTest extends TestCase
{
    private function servidor(?string $userAgent): Servidor
    {
        // last_user_agent não é mass-assignable (só o controller grava, via
        // forceFill) -- setar direto pra não depender do $fillable no teste.
        return tap(new Servidor, fn (Servidor $s) => $s->last_user_agent = $userAgent);
    }

    public function test_extracts_the_version_from_a_typical_user_agent(): void
    {
        $this->assertSame('1.22.0', $this->servidor('unbound/1.22.0')->unboundVersion());
        $this->assertSame('1.26.1', $this->servidor('Unbound/1.26.1')->unboundVersion());
    }

    public function test_returns_null_when_there_is_no_user_agent_or_it_is_not_unbound(): void
    {
        $this->assertNull($this->servidor(null)->unboundVersion());
        $this->assertNull($this->servidor('')->unboundVersion());
        $this->assertNull($this->servidor('Mozilla/5.0 (compatible; curl/8.5.0)')->unboundVersion());
    }

    public function test_known_affected_versions_are_flagged(): void
    {
        foreach (['1.13.1', '1.17.0', '1.22.0', '1.26.0'] as $versao) {
            $this->assertTrue($this->servidor("unbound/{$versao}")->unboundVersionAfetada(), "esperava {$versao} marcada como afetada");
        }
    }

    public function test_1_26_1_and_later_are_not_flagged(): void
    {
        foreach (['1.26.1', '1.26.2', '1.27.0', '2.0.0'] as $versao) {
            $this->assertFalse($this->servidor("unbound/{$versao}")->unboundVersionAfetada(), "esperava {$versao} NÃO afetada");
        }
    }

    public function test_unknown_version_is_never_flagged_as_affected(): void
    {
        $this->assertFalse($this->servidor(null)->unboundVersionAfetada());
        $this->assertFalse($this->servidor('curl/8.5.0')->unboundVersionAfetada());
    }
}
