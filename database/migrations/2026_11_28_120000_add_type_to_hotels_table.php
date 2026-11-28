<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            if (! Schema::hasColumn('hotels', 'type')) {
                $table->string('type', 20)->default('hotel')->after('name');
                $table->index(['tenant_id', 'type']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            if (Schema::hasColumn('hotels', 'type')) {
                $table->dropIndex(['tenant_id', 'type']);
                $table->dropColumn('type');
            }
        });
    }
};
