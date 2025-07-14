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
        Schema::create('shipment_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->onDelete('cascade');

            // FedEx Rate Information
            $table->string('service_type'); // e.g., 'STANDARD_OVERNIGHT', 'GROUND', etc.
            $table->decimal('base_rate', 10, 2);
            $table->decimal('handling_fee', 10, 2);
            $table->decimal('total_rate', 10, 2);
            $table->string('currency', 3)->default('USD');

            // Delivery Information
            $table->integer('transit_days')->nullable();
            $table->date('delivery_date')->nullable();
            $table->time('delivery_time')->nullable();

            // FedEx Response Data
            $table->text('fedex_rate_response')->nullable();
            $table->boolean('is_selected')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_rates');
    }
};
