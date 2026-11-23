<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->string('public_token', 48)->nullable()->unique()->after('status');
            $table->string('supplier_name', 160)->nullable()->after('total_amount');
            $table->decimal('supplier_cost', 12, 2)->default(0)->after('supplier_name');
            $table->timestamp('quote_approved_at')->nullable()->after('public_token');
            $table->string('quote_approved_by_name', 160)->nullable()->after('quote_approved_at');
            $table->unsignedInteger('portal_views_count')->default(0)->after('quote_approved_by_name');
            $table->timestamp('portal_last_viewed_at')->nullable()->after('portal_views_count');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->date('passport_expires_at')->nullable()->after('passport_number');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->json('trial_drip_steps_sent')->nullable()->after('trial_ends_at');
            $table->timestamp('last_activity_at')->nullable()->after('updated_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_login_at');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['trial_drip_steps_sent', 'last_activity_at']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('passport_expires_at');
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn([
                'public_token',
                'supplier_name',
                'supplier_cost',
                'quote_approved_at',
                'quote_approved_by_name',
                'portal_views_count',
                'portal_last_viewed_at',
            ]);
        });
    }
};
