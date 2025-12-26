<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $blueprint) {
            $blueprint->unsignedBigInteger('trip_id')->after('tenant_id')->nullable();
            $blueprint->foreignId('payment_id')->nullable()->change();
        });

        // Data migration: Set trip_id based on the first linked payment
        DB::table('receipts')
            ->join('payments', 'receipts.payment_id', '=', 'payments.id')
            ->update(['receipts.trip_id' => DB::raw('payments.trip_id')]);

        Schema::table('receipts', function (Blueprint $blueprint) {
            $blueprint->foreign('trip_id')->references('id')->on('trips')->cascadeOnDelete();
        });

        Schema::table('payments', function (Blueprint $blueprint) {
            $blueprint->foreignId('receipt_id')->nullable()->after('trip_id')->constrained()->nullOnDelete();
        });

        // Back-populate payments with their receipt_id
        DB::table('payments')
            ->join('receipts', 'payments.id', '=', 'receipts.payment_id')
            ->update(['payments.receipt_id' => DB::raw('receipts.id')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $blueprint) {
            $blueprint->dropForeign(['receipt_id']);
            $blueprint->dropColumn('receipt_id');
        });

        Schema::table('receipts', function (Blueprint $blueprint) {
            $blueprint->dropForeign(['trip_id']);
            $blueprint->dropColumn('trip_id');
            $blueprint->foreignId('payment_id')->nullable(false)->change();
        });
    }
};
