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
            // Add order_id column if it doesn't exist
            if (!Schema::hasColumn('payment_transactions', 'order_id')) {
                $table->string('order_id')->nullable()->after('paypal_order_id');
            }

            // Add transaction_id column if it doesn't exist
            if (!Schema::hasColumn('payment_transactions', 'transaction_id')) {
                $table->string('transaction_id')->nullable()->after('paypal_transaction_id');
            }

            // Add custom_id column if it doesn't exist
            if (!Schema::hasColumn('payment_transactions', 'custom_id')) {
                $table->string('custom_id')->nullable()->after('id');
            }

            // Add gateway_response column if it doesn't exist
            if (!Schema::hasColumn('payment_transactions', 'gateway_response')) {
                $table->text('gateway_response')->nullable()->after('paypal_response');
            }

            // Rename paypal_transaction_id to transaction_id if it exists
            if (Schema::hasColumn('payment_transactions', 'paypal_transaction_id') &&
                Schema::hasColumn('payment_transactions', 'transaction_id')) {
                $table->dropColumn('paypal_transaction_id');
            }

            // Rename paypal_order_id to order_id if it exists
            if (Schema::hasColumn('payment_transactions', 'paypal_order_id') &&
                Schema::hasColumn('payment_transactions', 'order_id')) {
                $table->dropColumn('paypal_order_id');
            }

            // Rename paypal_response to gateway_response if it exists
            if (Schema::hasColumn('payment_transactions', 'paypal_response') &&
                Schema::hasColumn('payment_transactions', 'gateway_response')) {
                $table->dropColumn('paypal_response');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            // Remove new columns
            if (Schema::hasColumn('payment_transactions', 'order_id')) {
                $table->dropColumn('order_id');
            }

            if (Schema::hasColumn('payment_transactions', 'transaction_id')) {
                $table->dropColumn('transaction_id');
            }

            if (Schema::hasColumn('payment_transactions', 'custom_id')) {
                $table->dropColumn('custom_id');
            }

            if (Schema::hasColumn('payment_transactions', 'gateway_response')) {
                $table->dropColumn('gateway_response');
            }

            // Restore old columns
            if (!Schema::hasColumn('payment_transactions', 'paypal_transaction_id')) {
                $table->string('paypal_transaction_id')->nullable();
            }

            if (!Schema::hasColumn('payment_transactions', 'paypal_order_id')) {
                $table->string('paypal_order_id')->nullable();
            }

            if (!Schema::hasColumn('payment_transactions', 'paypal_response')) {
                $table->text('paypal_response')->nullable();
            }
        });
    }
};
