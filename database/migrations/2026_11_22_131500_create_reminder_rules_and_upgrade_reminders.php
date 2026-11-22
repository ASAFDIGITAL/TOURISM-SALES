<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('trigger_type'); // days_before_trip, days_after_trip, days_before_payment_due, days_after_booking_unpaid
            $table->unsignedInteger('days_offset')->default(3);
            $table->string('recipient_type')->default('agent'); // agent, customer, both
            $table->string('channel')->default('push'); // push, email, sms, all
            $table->string('template_type')->default('pre_trip_reminder'); // trip_summary, payment_reminder, pre_trip_reminder, custom
            $table->string('message_language', 5)->default('he');
            $table->text('custom_message')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('reminder_rule_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reminder_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['reminder_rule_id', 'trip_id']);
        });

        Schema::table('reminders', function (Blueprint $table) {
            $table->string('category')->default('trip')->after('trip_id'); // trip, payment, general
            $table->string('recipient_type')->default('agent')->after('category'); // agent, customer, both
            $table->string('message_language', 5)->default('he')->after('channel');
        });
    }

    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->dropColumn(['category', 'recipient_type', 'message_language']);
        });

        Schema::dropIfExists('reminder_rule_logs');
        Schema::dropIfExists('reminder_rules');
    }
};
