<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UnboundCheckReleaseTest extends TestCase
{
    use RefreshDatabase;

    private function fakeRelease(string $tag, string $body): void
    {
        Http::fake([
            'api.github.com/repos/NLnetLabs/unbound/releases*' => Http::response([[
                'tag_name' => $tag,
                'body' => $body,
                'html_url' => "https://github.com/NLnetLabs/unbound/releases/tag/{$tag}",
            ]], 200),
        ]);
    }

    public function test_new_release_with_cve_in_changelog_logs_audit_and_stores_the_tag(): void
    {
        $this->fakeRelease('release-1.26.1', 'Fixes for CVE-2026-81642 and CVE-2026-33278.');

        $this->artisan('unbound:check-release')->assertExitCode(0);

        $this->assertDatabaseHas('audit_logs', ['action' => 'unbound.release.security']);
        $this->assertSame('release-1.26.1', Setting::get('unbound_last_checked_release'));
    }

    public function test_new_release_without_cve_mention_does_not_log_but_still_stores_the_tag(): void
    {
        $this->fakeRelease('release-1.27.0', 'New feature: faster cache eviction. Bug fixes.');

        $this->artisan('unbound:check-release')->assertExitCode(0);

        $this->assertDatabaseMissing('audit_logs', ['action' => 'unbound.release.security']);
        $this->assertSame('release-1.27.0', Setting::get('unbound_last_checked_release'));
    }

    public function test_same_release_as_last_checked_does_nothing(): void
    {
        Setting::set('unbound_last_checked_release', 'release-1.26.1');
        $this->fakeRelease('release-1.26.1', 'Fixes for CVE-2026-81642.');

        $this->artisan('unbound:check-release')->assertExitCode(0);

        $this->assertDatabaseMissing('audit_logs', ['action' => 'unbound.release.security']);
    }

    public function test_github_failure_does_not_crash_and_leaves_setting_untouched(): void
    {
        Http::fake([
            'api.github.com/repos/NLnetLabs/unbound/releases*' => Http::response([], 500),
        ]);

        $this->artisan('unbound:check-release')->assertExitCode(1);

        $this->assertNull(Setting::get('unbound_last_checked_release'));
    }
}
