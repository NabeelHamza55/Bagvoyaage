<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->foreignId('selected_rate_id')
                ->nullable()
                ->after('status')
                ->constrained('shipment_rates')
                ->nullOnDelete();
        });

        $shipmentIds = DB::table('shipments')->pluck('id');
        foreach ($shipmentIds as $sid) {
            $rid = DB::table('shipment_rates')
                ->where('shipment_id', $sid)
                ->where('is_selected', 1)
                ->value('id');
            if ($rid) {
                DB::table('shipments')->where('id', $sid)->update(['selected_rate_id' => $rid]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('selected_rate_id');
        });
    }
};
