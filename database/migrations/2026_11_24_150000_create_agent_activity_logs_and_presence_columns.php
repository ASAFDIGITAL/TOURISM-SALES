<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable()->after('last_login_at');
                $table->index('last_seen_at');
            }
            if (! Schema::hasColumn('users', 'last_ip')) {
                $table->string('last_ip', 45)->nullable()->after('last_seen_at');
            }
            if (! Schema::hasColumn('users', 'last_user_agent')) {
                $table->string('last_user_agent', 255)->nullable()->after('last_ip');
            }
            if (! Schema::hasColumn('users', 'last_page')) {
                $table->string('last_page', 255)->nullable()->after('last_user_agent');
            }
        });

        Schema::create('agent_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('event', 32)->index();
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('device_label', 80)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('path', 255)->nullable();
            $table->string('details', 255)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_activity_logs');

        Schema::table('users', function (Blueprint $table) {
            foreach (['last_page', 'last_user_agent', 'last_ip', 'last_seen_at'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
