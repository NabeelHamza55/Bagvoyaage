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
            'payment_completed_error',
            'shipment_created',
            'pickup_scheduled',
            'label_generated',
            'in_transit',
            'delivered'
        ) NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum values
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
