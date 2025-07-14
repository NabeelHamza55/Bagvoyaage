<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Shipment extends Model
{
    protected $fillable = [
        'origin_state',
        'destination_state',
        'sender_full_name',
        'sender_email',
        'sender_phone',
        'sender_address_line',
        'sender_city',
        'sender_state',
        'sender_zipcode',
        'delivery_method',
        'pickup_type',
        'packaging_type',
        'service_type',
        'pickup_address',
        'pickup_city',
        'pickup_state',
        'pickup_postal_code',
        'pickup_date',
        'pickup_ready_time',
        'pickup_close_time',
        'pickup_instructions',
        'recipient_name',
        'recipient_phone',
        'recipient_address',
        'recipient_city',
        'recipient_state',
        'recipient_postal_code',
        'package_length',
        'package_width',
        'package_height',
        'package_weight',
        'weight_unit',
        'dimension_unit',
        'package_description',
        'declared_value',
        'currency_code',
        'delivery_type',
        'preferred_ship_date',
        'tracking_number',
        'pickup_scheduled',
        'pickup_confirmation',
        'fedex_response',
        'status',
    ];

    protected $casts = [
        'preferred_ship_date' => 'date',
        'pickup_scheduled' => 'boolean',
        'fedex_response' => 'array',
    ];

    // Relationships
    public function rates(): HasMany
    {
        return $this->hasMany(ShipmentRate::class);
    }

    public function selectedRate(): HasOne
    {
        return $this->hasOne(ShipmentRate::class)->where('is_selected', true);
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function tracking(): HasMany
    {
        return $this->hasMany(ShipmentTracking::class);
    }

    // Helper methods
    public function getPackageVolume(): float
    {
        return $this->package_length * $this->package_width * $this->package_height;
    }

    public function getFullPickupAddress(): string
    {
        if ($this->delivery_method !== 'pickup') {
            return '';
        }

        return implode(', ', array_filter([
            $this->pickup_address,
            $this->pickup_city,
            $this->pickup_state,
            $this->pickup_postal_code,
        ]));
    }

    public function getFullRecipientAddress(): string
    {
        return implode(', ', array_filter([
            $this->recipient_address,
            $this->recipient_city,
            $this->recipient_state,
            $this->recipient_postal_code,
        ]));
    }
}
