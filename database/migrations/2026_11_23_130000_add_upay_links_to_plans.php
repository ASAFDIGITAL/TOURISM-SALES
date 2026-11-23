<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('upay_monthly_url', 500)->nullable()->after('yearly_price');
            $table->string('upay_yearly_url', 500)->nullable()->after('upay_monthly_url');
        });

        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->string('provider', 32)->default('payplus')->after('plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->dropColumn('provider');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['upay_monthly_url', 'upay_yearly_url']);
        });
    }
};
