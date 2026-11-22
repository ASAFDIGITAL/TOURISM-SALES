<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('subscription_type')->default('recurring')->after('status'); // recurring, lifetime
            $table->string('renewal_payment_url', 500)->nullable()->after('subscription_amount');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notify_expiring_tenants')->default(true)->after('notify_reminders');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notify_expiring_tenants');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['subscription_type', 'renewal_payment_url']);
        });
    }
};
