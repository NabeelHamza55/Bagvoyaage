<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentRate extends Model
{
    protected $fillable = [
        'shipment_id',
        'service_type',
        'base_rate',
        'handling_fee',
        'total_rate',
        'currency',
        'transit_days',
        'delivery_date',
        'delivery_time',
        'fedex_rate_response',
        'is_selected',
    ];

    protected $casts = [
        'base_rate' => 'decimal:2',
        'handling_fee' => 'decimal:2',
        'total_rate' => 'decimal:2',
        'delivery_date' => 'date',
        'delivery_time' => 'datetime',
        'is_selected' => 'boolean',
    ];

    // Relationships
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    // Helper methods
    public function getFormattedTotalRate(): string
    {
        return number_format($this->total_rate, 2) . ' ' . $this->currency;
    }

    public function getServiceDisplayName(): string
    {
        return match ($this->service_type) {
            'STANDARD_OVERNIGHT' => 'Standard Overnight',
            'PRIORITY_OVERNIGHT' => 'Priority Overnight',
            'FEDEX_GROUND' => 'FedEx Ground',
            'FEDEX_EXPRESS_SAVER' => 'FedEx Express Saver',
            'FEDEX_2_DAY' => 'FedEx 2Day',
            'FEDEX_2_DAY_AM' => 'FedEx 2Day AM',
            'FEDEX_STANDARD_OVERNIGHT' => 'FedEx Standard Overnight',
            'FEDEX_PRIORITY_OVERNIGHT' => 'FedEx Priority Overnight',
            'FEDEX_FIRST_OVERNIGHT' => 'FedEx First Overnight',
            'INTERNATIONAL_PRIORITY' => 'International Priority',
            'INTERNATIONAL_ECONOMY' => 'International Economy',
            'FEDEX_FREIGHT_PRIORITY' => 'FedEx Freight Priority',
            'FEDEX_FREIGHT_ECONOMY' => 'FedEx Freight Economy',
            'FEDEX_1_DAY_FREIGHT' => 'FedEx 1Day Freight',
            'FEDEX_2_DAY_FREIGHT' => 'FedEx 2Day Freight',
            'FEDEX_3_DAY_FREIGHT' => 'FedEx 3Day Freight',
            'SAME_DAY' => 'Same Day',
            'SAME_DAY_CITY' => 'Same Day City',
            'SAME_DAY_METRO_AFTERNOON' => 'Same Day Metro Afternoon',
            'SAME_DAY_METRO_MORNING' => 'Same Day Metro Morning',
            'SAME_DAY_METRO_RUSH' => 'Same Day Metro Rush',
            default => str_replace('_', ' ', $this->service_type),
        };
    }
}
