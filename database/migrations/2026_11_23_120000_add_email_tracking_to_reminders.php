<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->string('recipient_email', 255)->nullable()->after('channel');
            $table->string('tracking_token', 64)->nullable()->unique()->after('recipient_email');
            $table->timestamp('sent_at')->nullable()->after('scheduled_at');
            $table->timestamp('opened_at')->nullable()->after('sent_at');
            $table->timestamp('last_opened_at')->nullable()->after('opened_at');
            $table->unsignedInteger('open_count')->default(0)->after('last_opened_at');
            $table->text('error_message')->nullable()->after('message');
        });

        Schema::table('reminder_rule_logs', function (Blueprint $table) {
            $table->foreignId('reminder_id')
                ->nullable()
                ->after('trip_id')
                ->constrained('reminders')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reminder_rule_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reminder_id');
        });

        Schema::table('reminders', function (Blueprint $table) {
            $table->dropColumn([
                'recipient_email',
                'tracking_token',
                'sent_at',
                'opened_at',
                'last_opened_at',
                'open_count',
                'error_message',
            ]);
        });
    }
};
