<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('payplus_customer_uid')->nullable()->after('renewal_payment_url');
            $table->string('payplus_recurring_uid')->nullable()->index()->after('payplus_customer_uid');
            $table->string('payplus_billing_cycle', 16)->nullable()->after('payplus_recurring_uid');
            $table->string('payplus_status', 24)->nullable()->after('payplus_billing_cycle');
            $table->string('payplus_card_last4', 8)->nullable()->after('payplus_status');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('email');
        });

        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('billing_cycle', 16)->default('monthly'); // monthly | yearly | one_time
            $table->string('status', 24)->default('pending'); // pending | paid | failed | refunded
            $table->decimal('subtotal_amount', 10, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(18.00);
            $table->decimal('vat_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('currency', 8)->default('ILS');
            $table->unsignedInteger('extra_seats')->default(0);
            $table->string('payplus_page_request_uid')->nullable()->index();
            $table->string('payplus_transaction_uid')->nullable()->unique();
            $table->string('payplus_recurring_uid')->nullable()->index();
            $table->string('payplus_invoice_number')->nullable();
            $table->string('payplus_invoice_url', 500)->nullable();
            $table->string('description')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_invoices');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropColumn('google_id');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['payplus_recurring_uid']);
            $table->dropColumn([
                'payplus_customer_uid',
                'payplus_recurring_uid',
                'payplus_billing_cycle',
                'payplus_status',
                'payplus_card_last4',
            ]);
        });
    }
};
