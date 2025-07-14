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
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('origin_state', 2);
            $table->string('destination_state', 2);
            $table->string('sender_full_name');
            $table->string('sender_email');
            $table->string('sender_phone', 20);
            $table->enum('delivery_method', ['pickup', 'dropoff']);
            $table->text('pickup_address')->nullable();
            $table->string('pickup_city')->nullable();
            $table->string('pickup_state', 2)->nullable();
            $table->string('pickup_postal_code', 10)->nullable();
            $table->string('recipient_name');
            $table->string('recipient_phone', 20);
            $table->text('recipient_address');
            $table->string('recipient_city');
            $table->string('recipient_state', 2);
            $table->string('recipient_postal_code', 10);
            $table->decimal('package_length', 8, 2);
            $table->decimal('package_width', 8, 2);
            $table->decimal('package_height', 8, 2);
            $table->decimal('package_weight', 8, 2);
            $table->text('package_description');
            $table->enum('delivery_type', ['standard', 'express', 'overnight']);
            $table->date('preferred_ship_date');
            $table->string('tracking_number')->nullable();
            $table->boolean('pickup_scheduled')->default(false);
            $table->string('pickup_confirmation')->nullable();
            $table->json('fedex_response')->nullable();
            $table->enum('status', ['pending', 'quote_received', 'payment_pending', 'paid', 'confirmed', 'failed']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
