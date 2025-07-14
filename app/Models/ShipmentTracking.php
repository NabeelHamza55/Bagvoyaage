<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentTracking extends Model
{
    protected $fillable = [
        'shipment_id',
        'tracking_number',
        'status',
        'status_description',
        'location',
        'event_datetime',
        'fedex_tracking_response',
    ];

    protected $casts = [
        'event_datetime' => 'datetime',
    ];

    // Relationships
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    // Helper methods
    public function getFormattedEventDate(): string
    {
        return $this->event_datetime ? $this->event_datetime->format('M j, Y g:i A') : '';
    }

    public function getStatusDisplayName(): string
    {
        return match ($this->status) {
            'in_transit' => 'In Transit',
            'delivered' => 'Delivered',
            'exception' => 'Exception',
            'pending' => 'Pending',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }
}
