<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('monthly_email_limit')->nullable()->after('allowed_channels');
            $table->unsignedInteger('monthly_sms_limit')->nullable()->after('monthly_email_limit');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedInteger('extra_email_quota')->default(0)->after('extra_seats');
            $table->unsignedInteger('extra_sms_quota')->default(0)->after('extra_email_quota');
        });

        Schema::table('reminder_rule_logs', function (Blueprint $table) {
            $table->string('channels_sent', 100)->nullable()->after('sent_at');
            $table->string('status', 32)->default('sent')->after('channels_sent');
            $table->string('note', 255)->nullable()->after('status');
        });

        // Populate default monthly limits for existing 3 plans (Start / Pro / Business)
        $defaults = [
            'basic' => ['email' => 100, 'sms' => 50],
            'pro' => ['email' => 300, 'sms' => 100],
            'business' => ['email' => 500, 'sms' => 180],
        ];

        foreach ($defaults as $slug => $limits) {
            DB::table('plans')
                ->where('slug', $slug)
                ->update([
                    'monthly_email_limit' => $limits['email'],
                    'monthly_sms_limit' => $limits['sms'],
                    'allowed_channels' => json_encode(['push', 'email', 'whatsapp', 'sms']),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('reminder_rule_logs', function (Blueprint $table) {
            $table->dropColumn(['channels_sent', 'status', 'note']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['extra_email_quota', 'extra_sms_quota']);
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['monthly_email_limit', 'monthly_sms_limit']);
        });
    }
};
