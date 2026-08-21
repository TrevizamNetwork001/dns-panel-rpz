<?php

namespace Tests\Unit;

use App\Models\AuditLog;
use Tests\TestCase;

class AuditLogBucketTest extends TestCase
{
    public function test_failed_auth_actions_are_danger(): void
    {
        $this->assertSame('danger', AuditLog::bucket('auth.login_failed'));
        $this->assertSame('danger', AuditLog::bucket('security.unauthorized_access'));
    }

    public function test_successful_auth_actions_are_warning(): void
    {
        $this->assertSame('warning', AuditLog::bucket('auth.login'));
        $this->assertSame('warning', AuditLog::bucket('auth.logout'));
    }

    public function test_security_ban_events_are_warning(): void
    {
        $this->assertSame('warning', AuditLog::bucket('security.ip_banned'));
    }

    public function test_ip_management_actions_are_info(): void
    {
        $this->assertSame('info', AuditLog::bucket('servidor.ips.added'));
        $this->assertSame('info', AuditLog::bucket('sugestao.created'));
    }

    public function test_destructive_actions_are_warning(): void
    {
        $this->assertSame('warning', AuditLog::bucket('servidor.destroyed'));
    }

    public function test_sugestao_actions_are_always_info_even_when_rejected(): void
    {
        // "sugestao." prefix is checked before the destructive-keyword check, so it wins.
        $this->assertSame('info', AuditLog::bucket('sugestao.rejected'));
        $this->assertSame('info', AuditLog::bucket('sugestao.approved'));
    }

    public function test_generic_actions_are_muted(): void
    {
        $this->assertSame('muted', AuditLog::bucket('servidor.updated'));
    }
}
