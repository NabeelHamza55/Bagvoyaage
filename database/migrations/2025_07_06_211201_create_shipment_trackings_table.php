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
        Schema::create('shipment_trackings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->onDelete('cascade');

            // Tracking Information
            $table->string('tracking_number');
            $table->string('status');
            $table->text('status_description')->nullable();
            $table->string('location')->nullable();
            $table->datetime('event_datetime')->nullable();

            // FedEx Tracking Response
            $table->text('fedex_tracking_response')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_trackings');
    }
};
