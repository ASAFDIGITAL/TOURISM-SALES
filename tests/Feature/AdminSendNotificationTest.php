<?php

namespace Tests\Feature;

use App\Filament\Actions\SendAdminNotificationAction;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InforuSmsService;
use App\Services\WebPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class AdminSendNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_send_direct_notification_across_bell_push_sms_and_email(): void
    {
        Mail::fake();

        $tenant = Tenant::create([
            'name' => 'Atlas Travel',
            'email' => 'office@atlas.test',
            'phone' => '0507778888',
            'status' => 'active',
        ]);

        $agent = User::create([
            'name' => 'Rami Agent',
            'email' => 'rami@atlas.test',
            'phone' => '0523334444',
            'password' => Hash::make('password123'),
            'role' => 'agent',
            'tenant_id' => $tenant->id,
        ]);

        $mockPush = Mockery::mock(WebPushService::class);
        $mockPush->shouldReceive('sendToUser')
            ->once()
            ->andReturn(1);
        $this->app->instance(WebPushService::class, $mockPush);

        $mockSms = Mockery::mock(InforuSmsService::class);
        $mockSms->shouldReceive('sendSms')
            ->twice() // user phone + tenant phone (different numbers)
            ->andReturn(true);
        $this->app->instance(InforuSmsService::class, $mockSms);

        SendAdminNotificationAction::dispatchNotifications(collect([$tenant]), [
            'title' => 'עדכון חשוב',
            'body' => 'פיצ\'ר חדש זמין כעת במערכת!',
            'style' => 'info',
            'channels' => ['database', 'push', 'sms', 'email'],
        ]);

        $this->assertSame(1, $agent->notifications()->count());
        $data = $agent->notifications()->first()->data;
        $this->assertSame('עדכון חשוב', $data['title']);
    }
}
