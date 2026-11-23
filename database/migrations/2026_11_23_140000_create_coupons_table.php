<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 120)->nullable();
            $table->string('discount_type', 16)->default('percent'); // percent | fixed
            $table->decimal('discount_value', 10, 2);
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('billing_cycle', 16)->nullable(); // monthly | yearly | null (both)
            $table->string('upay_override_url', 500)->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('coupon_id')
                ->nullable()
                ->after('plan_id')
                ->constrained('coupons')
                ->nullOnDelete();
        });

        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->foreignId('coupon_id')
                ->nullable()
                ->after('plan_id')
                ->constrained('coupons')
                ->nullOnDelete();
            $table->decimal('discount_amount', 10, 2)->default(0)->after('subtotal_amount');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn('discount_amount');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
        });

        Schema::dropIfExists('coupons');
    }
};
