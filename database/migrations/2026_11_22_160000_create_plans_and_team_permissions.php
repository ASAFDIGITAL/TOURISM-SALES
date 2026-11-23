<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name_he');
            $table->string('name_ar')->nullable();
            $table->string('name_en');
            $table->text('description_he')->nullable();
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->decimal('monthly_price', 10, 2)->default(0);
            $table->decimal('yearly_price', 10, 2)->default(0);
            $table->string('currency', 8)->default('ILS');
            $table->unsignedInteger('included_users')->default(1);
            $table->decimal('extra_user_price', 10, 2)->default(49);
            $table->unsignedInteger('trial_days')->default(14);
            $table->boolean('reminders_enabled')->default(true);
            $table->json('allowed_channels')->nullable();
            $table->boolean('can_use_employee_reports')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('subscription_type')->constrained('plans')->nullOnDelete();
            $table->unsignedInteger('extra_seats')->default(0)->after('plan_id');
            $table->boolean('is_trial')->default(false)->after('extra_seats');
            $table->timestamp('trial_ends_at')->nullable()->after('is_trial');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('agency_role', 24)->default('owner')->after('role'); // owner | staff
            $table->json('permissions')->nullable()->after('agency_role');
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->foreignId('created_by_user_id')->nullable()->after('tenant_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('created_by_user_id')->nullable()->after('tenant_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_user_id');
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['agency_role', 'permissions']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn(['extra_seats', 'is_trial', 'trial_ends_at']);
        });

        Schema::dropIfExists('plans');
    }
};
