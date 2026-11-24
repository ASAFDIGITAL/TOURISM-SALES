<?php

namespace Tests\Feature;

use App\Models\AgentActivityLog;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OnlineAgentsAndLogsTest extends TestCase
{
    use RefreshDatabase;

    private function createSetup(): array
    {
        Plan::ensureDefaultPlansExist();
        $pro = Plan::where('slug', 'pro')->firstOrFail();

        $tenant = Tenant::create([
            'name' => 'Atlas Travel',
            'email' => 'atlas@travel.test',
            'phone' => '050-1112233',
            'status' => 'active',
            'plan_id' => $pro->id,
            'subscription_type' => 'lifetime',
            'language' => 'he',
            'currency' => 'ILS',
        ]);

        $agent = User::create([
            'name' => 'Rami Levi',
            'email' => 'rami@travel.test',
            'password' => Hash::make('password123'),
            'role' => 'agent',
            'agency_role' => User::AGENCY_ROLE_OWNER,
            'tenant_id' => $tenant->id,
            'language' => 'he',
        ]);

        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@system.test',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
            'language' => 'he',
        ]);

        return [$tenant, $agent, $admin];
    }

    public function test_agent_presence_ip_changes_and_login_logout_logs_are_tracked_and_visible_to_admin(): void
    {
        Cache::flush();
        [$tenant, $agent, $admin] = $this->createSetup();

        // 1. Agent logs in -> immediately online with login log
        Auth::login($agent);

        $agent->refresh();
        $this->assertTrue($agent->isOnlineNow());
        $this->assertDatabaseHas('agent_activity_logs', [
            'user_id' => $agent->id,
            'tenant_id' => $tenant->id,
            'event' => AgentActivityLog::EVENT_LOGIN,
        ]);

        // 2. Agent visits /agent/trips from an iPhone with a Cloudflare client IP
        Cache::forget('presence_user_' . $agent->id);
        $this->actingAs($agent)
            ->withHeaders([
                'CF-Connecting-IP' => '84.108.45.21',
                'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
            ])
            ->get('/agent/trips')
            ->assertOk();

        $agent->refresh();
        $this->assertSame('84.108.45.21', $agent->last_ip);
        $this->assertSame('/agent/trips', $agent->last_page);
        $this->assertTrue($agent->isOnlineNow());

        // 3. Agent switches IP on next request -> ip_changed event logged immediately
        $this->actingAs($agent)
            ->withHeaders([
                'CF-Connecting-IP' => '79.177.12.99',
                'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
            ])
            ->get('/agent/customers')
            ->assertOk();

        $agent->refresh();
        $this->assertSame('79.177.12.99', $agent->last_ip);
        $this->assertSame('/agent/customers', $agent->last_page);
        $this->assertDatabaseHas('agent_activity_logs', [
            'user_id' => $agent->id,
            'event' => AgentActivityLog::EVENT_IP_CHANGED,
            'ip_address' => '79.177.12.99',
            'device_label' => 'iPhone · Safari',
        ]);

        // 4. Super Admin sees online agent & IP on both /admin dashboard and /admin/agent-activity-logs
        $this->flushSession();
        $this->actingAs($admin);

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Rami Levi')
            ->assertSee('Atlas Travel')
            ->assertSee('79.177.12.99')
            ->assertSee('iPhone · Safari');

        $this->get('/admin/agent-activity-logs')
            ->assertOk()
            ->assertSee('Rami Levi')
            ->assertSee('79.177.12.99')
            ->assertSee('/agent/customers');

        // 5. Agent logs out -> marked offline immediately and logout logged
        $this->flushSession();
        Auth::login($agent);
        Auth::logout();

        $agent->refresh();
        $this->assertFalse($agent->isOnlineNow());
        $this->assertDatabaseHas('agent_activity_logs', [
            'user_id' => $agent->id,
            'event' => AgentActivityLog::EVENT_LOGOUT,
        ]);

        // 6. Failed login attempt is logged with IP and attempted email
        Auth::attempt(['email' => 'rami@travel.test', 'password' => 'wrong-password']);
        $this->assertDatabaseHas('agent_activity_logs', [
            'user_id' => $agent->id,
            'tenant_id' => $tenant->id,
            'event' => AgentActivityLog::EVENT_LOGIN_FAILED,
        ]);

        // 7. Record deletion (e.g. deleting a Customer) is logged with actor & details
        $this->flushSession();
        $this->actingAs($agent);
        $customer = \App\Models\Customer::create([
            'tenant_id' => $tenant->id,
            'name' => 'Sensitive Client',
            'email' => 'client@test.com',
        ]);
        $customer->delete();

        $this->assertDatabaseHas('agent_activity_logs', [
            'user_id' => $agent->id,
            'tenant_id' => $tenant->id,
            'event' => AgentActivityLog::EVENT_RECORD_DELETED,
        ]);

        // 8. Admin impersonation enter + leave are both logged for full transparency
        $this->flushSession();
        $this->actingAs($admin)
            ->post('/admin/impersonate/tenant/' . $tenant->id)
            ->assertRedirect('/agent');

        $this->assertDatabaseHas('agent_activity_logs', [
            'user_id' => $admin->id,
            'tenant_id' => $tenant->id,
            'event' => AgentActivityLog::EVENT_IMPERSONATE_START,
        ]);

        $this->post('/admin/impersonate/leave')->assertRedirect('/admin');
        $this->assertDatabaseHas('agent_activity_logs', [
            'user_id' => $admin->id,
            'tenant_id' => $tenant->id,
            'event' => AgentActivityLog::EVENT_IMPERSONATE_END,
        ]);

        // 9. Audit logs are immutable (cannot be deleted or edited from UI)
        $log = AgentActivityLog::firstOrFail();
        $this->assertFalse(\App\Filament\Resources\AgentActivityLogResource::canCreate());
        $this->assertFalse(\App\Filament\Resources\AgentActivityLogResource::canEdit($log));
        $this->assertFalse(\App\Filament\Resources\AgentActivityLogResource::canDelete($log));
        $this->assertFalse(\App\Filament\Resources\AgentActivityLogResource::canDeleteAny());

        // 10. Automatic 6-month retention pruning deletes logs older than 6 months and keeps recent ones
        $oldLog = AgentActivityLog::create([
            'user_id' => $agent->id,
            'tenant_id' => $tenant->id,
            'event' => AgentActivityLog::EVENT_LOGIN,
            'ip_address' => '1.2.3.4',
            'created_at' => now()->subMonths(7),
        ]);

        $this->artisan('model:prune', ['--model' => [AgentActivityLog::class]])
            ->assertExitCode(0);

        $this->assertDatabaseMissing('agent_activity_logs', ['id' => $oldLog->id]);
        $this->assertDatabaseHas('agent_activity_logs', ['id' => $log->id]);
    }
}
