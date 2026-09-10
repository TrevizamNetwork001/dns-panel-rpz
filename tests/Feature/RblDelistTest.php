<?php

namespace Tests\Feature;

use App\Models\RblDelistRequest;
use App\Models\RblEvent;
use App\Models\RblList;
use App\Models\RblTarget;
use App\Models\User;
use Database\Seeders\RblListSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RblDelistTest extends TestCase
{
    use RefreshDatabase;

    private function event(array $listData = []): RblEvent
    {
        $target = RblTarget::create(['name' => 'Bloco CGNAT', 'type' => 'cidr', 'value' => '203.0.113.0/30', 'enabled' => true]);
        $list = RblList::create(array_merge(['name' => 'Example ZEN', 'type' => 'ip', 'dns_zone' => 'rbl.example.org', 'enabled' => true], $listData));

        return RblEvent::create(['rbl_target_id' => $target->id, 'rbl_list_id' => $list->id, 'last_checked_value' => '203.0.113.2', 'status' => 'open', 'first_seen_at' => '2026-09-10 08:40:00', 'last_seen_at' => '2026-09-10 09:53:00', 'last_response' => '127.0.0.4']);
    }

    public function test_model_relations_and_validated_status(): void
    {
        $event = $this->event();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('rbl.events.delist.store', $event), ['status' => 'requested'])->assertRedirect();
        $request = RblDelistRequest::first();
        $this->assertTrue($request->event->is($event));
        $this->assertTrue($request->user->is($admin));
        $this->assertTrue($event->delistRequests->contains($request));
        $this->assertNotNull($request->requested_at);
        $this->postJson(route('rbl.events.delist.store', $event), ['status' => 'invented'])->assertUnprocessable();
    }

    public function test_routes_require_admin(): void
    {
        $event = $this->event();
        $url = route('rbl.events.delist.store', $event);
        $this->post($url, ['status' => 'requested'])->assertRedirect('/login');
        $this->actingAs(User::factory()->cliente()->create())->post($url, ['status' => 'requested'])->assertForbidden();
        $this->assertDatabaseCount('rbl_delist_requests', 0);
    }

    public function test_event_panel_actions_audit_and_technical_state_are_independent(): void
    {
        $event = $this->event(['lookup_url' => 'https://lookup.example.test/ip', 'delist_url' => 'https://delist.example.test/', 'delist_instructions' => 'Siga o fluxo oficial.']);
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('rbl.events.show', $event))->assertOk()
            ->assertSee('Delist assistido')->assertSee('https://lookup.example.test/ip')->assertSee('Siga o fluxo oficial.')
            ->assertSee('203.0.113.2')->assertSee('Example ZEN')->assertSee('127.0.0.4')->assertSee('10/09/2026 08:40')
            ->assertDontSee('secret-test-token');

        foreach (['instructions_viewed', 'requested', 'waiting', 'accepted', 'rejected', 'not_applicable'] as $status) {
            $this->post(route('rbl.events.delist.store', $event), ['status' => $status])->assertRedirect();
            $this->assertDatabaseHas('rbl_delist_requests', ['rbl_event_id' => $event->id, 'user_id' => $admin->id, 'status' => $status]);
        }
        $current = RblDelistRequest::latest('id')->first();
        $this->patch(route('rbl.events.delist.update', [$event, $current]), ['status' => 'waiting', 'protocol' => '=PROTO-123', 'request_url' => 'https://provider.example.test/request/123', 'notes' => 'Nota privada'])->assertRedirect();
        $this->assertDatabaseHas('rbl_delist_requests', ['id' => $current->id, 'status' => 'waiting', 'protocol' => '=PROTO-123']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'rbl.delist.instructions_viewed', 'target_id' => $event->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'rbl.delist.requested', 'target_id' => $event->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'rbl.delist.accepted', 'target_id' => $event->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'rbl.delist.rejected', 'target_id' => $event->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'rbl.delist.not_applicable', 'target_id' => $event->id]);
        $this->assertDatabaseMissing('audit_logs', ['description' => 'Nota privada']);
        $this->assertDatabaseCount('rbl_alerts', 0);
        $this->assertDatabaseHas('rbl_events', ['id' => $event->id, 'status' => 'open', 'resolved_at' => null]);
    }

    public function test_report_csv_and_list_guidance(): void
    {
        $event = $this->event(['delist_instructions' => 'Revisão manual.']);
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('rbl.events.delist.store', $event), ['status' => 'requested', 'protocol' => '=ABC'])->assertRedirect();
        $this->get(route('rbl.events.report', $event))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertSee('Delist assistido')->assertSee('=ABC')->assertSee($admin->name);
        $csv = $this->get('/rbl/reports?format=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('delist_status', $csv);
        $this->assertStringContainsString('requested', $csv);
        $this->assertStringContainsString("'=ABC", $csv);

        $this->patch(route('rbl.lists.guidance.update', $event->list), ['lookup_url' => 'https://official.example.test/check', 'delist_url' => '', 'delist_instructions' => 'Orientação revisada.', 'delist_requires_manual_review' => '1'])->assertRedirect();
        $this->assertDatabaseHas('rbl_lists', ['id' => $event->rbl_list_id, 'lookup_url' => 'https://official.example.test/check', 'delist_instructions' => 'Orientação revisada.', 'delist_requires_manual_review' => true]);
    }

    public function test_seeder_adds_only_missing_guidance_and_is_idempotent(): void
    {
        $this->seed(RblListSeeder::class);
        $spamhaus = RblList::where('dns_zone', 'zen.spamhaus.org')->firstOrFail();
        $spamhaus->update(['name' => 'Nome customizado', 'enabled' => false, 'delist_instructions' => 'Fluxo interno customizado.']);
        $this->seed(RblListSeeder::class);
        $this->assertDatabaseCount('rbl_lists', 4);
        $this->assertDatabaseHas('rbl_lists', ['id' => $spamhaus->id, 'name' => 'Nome customizado', 'enabled' => false, 'delist_instructions' => 'Fluxo interno customizado.']);
    }
}
