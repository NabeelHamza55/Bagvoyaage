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
        // For MySQL, we need to modify the column directly with DB statement
        DB::statement("ALTER TABLE shipments MODIFY COLUMN status ENUM(
            'pending',
            'quote_received',
            'payment_pending',
            'paid',
            'confirmed',
            'failed',
            'payment_completed',
            'payment_completed_shipment_pending',
            'payment_completed_error'
        ) NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First update any rows with new statuses to prevent constraint violations
        DB::table('shipments')
            ->whereIn('status', ['payment_completed', 'payment_completed_shipment_pending', 'payment_completed_error'])
            ->update(['status' => 'paid']);

        // Revert the enum values to the original set
        DB::statement("ALTER TABLE shipments MODIFY COLUMN status ENUM(
            'pending',
            'quote_received',
            'payment_pending',
            'paid',
            'confirmed',
            'failed'
        ) NOT NULL DEFAULT 'pending'");
    }
};
