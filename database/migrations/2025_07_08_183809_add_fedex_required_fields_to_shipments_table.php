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
        Schema::table('shipments', function (Blueprint $table) {
            // Sender Address Information (Required for FedEx API)
            $table->text('sender_address_line')->after('sender_phone');
            $table->string('sender_city')->after('sender_address_line');
            $table->string('sender_state', 2)->after('sender_city');
            $table->string('sender_zipcode', 10)->after('sender_state');

            // Package Value and Currency (Required for FedEx API)
            $table->decimal('declared_value', 10, 2)->default(100.00)->after('package_description');
            $table->string('currency_code', 3)->default('USD')->after('declared_value');

            // Weight and Dimension Units (Required for FedEx API)
            $table->string('weight_unit', 2)->default('LB')->after('package_weight');
            $table->string('dimension_unit', 2)->default('IN')->after('weight_unit');

            // Shipping Options (Required for FedEx API)
            $table->enum('pickup_type', ['PICKUP', 'DROPOFF'])->default('DROPOFF')->after('delivery_method');
            $table->string('packaging_type')->default('YOUR_PACKAGING')->after('pickup_type');
            $table->string('service_type')->nullable()->after('packaging_type');

            // Pickup Scheduling Fields (Required if pickup_type = PICKUP)
            $table->date('pickup_date')->nullable()->after('preferred_ship_date');
            $table->time('pickup_ready_time')->nullable()->after('pickup_date');
            $table->time('pickup_close_time')->nullable()->after('pickup_ready_time');
            $table->text('pickup_instructions')->nullable()->after('pickup_close_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn([
                'sender_address_line',
                'sender_city',
                'sender_state',
                'sender_zipcode',
                'declared_value',
                'currency_code',
                'weight_unit',
                'dimension_unit',
                'pickup_type',
                'packaging_type',
                'service_type',
                'pickup_date',
                'pickup_ready_time',
                'pickup_close_time',
                'pickup_instructions'
            ]);
        });
    }
};
