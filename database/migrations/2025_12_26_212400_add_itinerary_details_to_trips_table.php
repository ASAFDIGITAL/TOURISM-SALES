<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->json('hotels')->nullable()->after('destination');
            $table->json('flights')->nullable()->after('hotels');
            $table->json('passengers')->nullable()->after('flights');
            $table->text('trip_summary')->nullable()->after('passengers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['hotels', 'flights', 'passengers', 'trip_summary']);
        });
    }
};
