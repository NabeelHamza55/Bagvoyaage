<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'shipment_id',
        'custom_id',
        'transaction_id',
        'order_id',
        'amount',
        'currency',
        'status',
        'payment_method',
        'gateway_response',
        'error_response',
        'gateway_data',
        'paid_at',
        // Legacy fields for backward compatibility
        'paypal_transaction_id',
        'paypal_order_id',
        'paypal_response',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    // Relationships
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    // Helper methods
    public function getFormattedAmount(): string
    {
        return number_format($this->amount, 2) . ' ' . $this->currency;
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isFailed(): bool
    {
        return in_array($this->status, ['failed', 'cancelled']);
    }
}
