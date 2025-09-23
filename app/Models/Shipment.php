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
        'pickup_time_slot',
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
        'bag_type',
        'number_of_bags',
        'declared_value',
        'currency_code',
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
    public function getBagSpecifications(): array
    {
        $specs = [
            'small' => [
                'name' => 'Small Bag',
                'dimensions' => '18" x 14" x 4"',
                'weight' => 25,
                'length' => 18,
                'width' => 14,
                'height' => 4
            ],
            'medium' => [
                'name' => 'Medium Bag',
                'dimensions' => '24" x 16" x 6"',
                'weight' => 40,
                'length' => 24,
                'width' => 16,
                'height' => 6
            ],
            'large' => [
                'name' => 'Large Bag',
                'dimensions' => '28" x 20" x 8"',
                'weight' => 55,
                'length' => 28,
                'width' => 20,
                'height' => 8
            ]
        ];

        return $specs[$this->bag_type] ?? $specs['small'];
    }

    public function getTotalWeight(): float
    {
        if ($this->bag_type && $this->number_of_bags) {
            $specs = $this->getBagSpecifications();
            return $specs['weight'] * $this->number_of_bags;
        }
        return $this->package_weight;
    }

    public function getTotalDimensions(): array
    {
        if ($this->bag_type && $this->number_of_bags) {
            $specs = $this->getBagSpecifications();
            return [
                'length' => $specs['length'],
                'width' => $specs['width'],
                'height' => $specs['height']
            ];
        }
        return [
            'length' => $this->package_length,
            'width' => $this->package_width,
            'height' => $this->package_height
        ];
    }
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
