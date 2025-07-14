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
        Schema::table('payment_transactions', function (Blueprint $table) {
            // Add webhook-related fields
            $table->string('custom_id')->nullable()->after('shipment_id');
            $table->string('order_id')->nullable()->after('paypal_order_id');
            $table->string('transaction_id')->nullable()->after('paypal_transaction_id');
            $table->text('gateway_response')->nullable()->after('paypal_response');

            // Update status enum to include more states
            $table->enum('status', ['pending', 'approved', 'completed', 'cancelled', 'failed', 'refunded'])->default('pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn(['custom_id', 'order_id', 'transaction_id', 'gateway_response']);

            // Revert status enum
            $table->enum('status', ['pending', 'approved', 'completed', 'cancelled', 'failed'])->default('pending')->change();
        });
    }
};
