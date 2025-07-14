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
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->onDelete('cascade');

            // PayPal Transaction Information
            $table->string('paypal_transaction_id')->unique()->nullable();
            $table->string('paypal_order_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');

            // Payment Status
            $table->enum('status', ['pending', 'approved', 'completed', 'cancelled', 'failed'])->default('pending');
            $table->enum('payment_method', ['paypal', 'card'])->default('paypal');

            // PayPal Response Data
            $table->text('paypal_response')->nullable();
            $table->text('error_response')->nullable();

            // Timestamps
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
