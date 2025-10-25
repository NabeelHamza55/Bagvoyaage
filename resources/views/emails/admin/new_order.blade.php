@extends('emails.layout')

@section('content')
<div style="text-align: center; margin-bottom: 32px;">
    <div class="status-badge status-admin">New Order Alert</div>
</div>

<h2 style="color: #1f2937; font-size: 24px; font-weight: 700; margin: 0 0 16px 0;">
    New Shipment Order
</h2>

<p style="margin: 0 0 24px 0; font-size: 16px; line-height: 1.6;">
    A new shipment order has been placed and processed successfully. Please review the details below.
</p>

<div class="shipping-details">
    <h3 style="margin: 0 0 16px 0; color: #374151; font-size: 18px; font-weight: 600;">
        Order Information
    </h3>

    <div class="detail-row">
        <span class="detail-label">Order ID:</span>
        <span class="detail-value">#{{ $shipment->id }}</span>
    </div>

    <div class="detail-row">
        <span class="detail-label">Tracking Number:</span>
        <span class="detail-value tracking-number">{{ $trackingNumber }}</span>
    </div>

    <div class="detail-row">
        <span class="detail-label">Service Type:</span>
        <span class="detail-value">{{ $shipment->selectedRate->service_type ?? 'FedEx Service' }}</span>
    </div>

    <div class="detail-row">
        <span class="detail-label">Total Cost:</span>
        <span class="detail-value">${{ $shipment->selectedRate ? number_format($shipment->selectedRate->total_rate, 2) : 'N/A' }}</span>
    </div>

    <div class="detail-row">
        <span class="detail-label">Order Date:</span>
        <span class="detail-value">{{ $shipment->created_at ? $shipment->created_at->format('F j, Y g:i A') : 'N/A' }}</span>
    </div>

    @if($shipment->pickup_type === 'PICKUP')
    <div class="detail-row">
        <span class="detail-label">Pickup Date:</span>
        <span class="detail-value">{{ $shipment->pickup_date ? \Carbon\Carbon::parse($shipment->pickup_date)->format('F j, Y') : 'Scheduled' }}</span>
    </div>
    @if($shipment->pickup_confirmation)
    <div class="detail-row">
        <span class="detail-label">Pickup Confirmation:</span>
        <span class="detail-value">{{ $shipment->pickup_confirmation }}</span>
    </div>
    @endif
    @endif
</div>

<div style="margin: 32px 0;">
    <h3 style="color: #374151; font-size: 18px; font-weight: 600; margin: 0 0 16px 0;">
        Customer Information
    </h3>
    <div style="background-color: #f9fafb; padding: 16px; border-radius: 8px; border: 1px solid #e5e7eb;">
        <p style="margin: 0; font-size: 14px; line-height: 1.5;">
            <strong>{{ $shipment->sender_full_name }}</strong><br>
            {{ $shipment->sender_address_line }}<br>
            {{ $shipment->sender_city }}, {{ $shipment->sender_state }} {{ $shipment->sender_zipcode }}<br>
            {{ $shipment->sender_phone }}<br>
            {{ $shipment->sender_email }}
        </p>
    </div>
</div>

<div style="margin: 32px 0;">
    <h3 style="color: #374151; font-size: 18px; font-weight: 600; margin: 0 0 16px 0;">
        Recipient Information
    </h3>
    <div style="background-color: #f9fafb; padding: 16px; border-radius: 8px; border: 1px solid #e5e7eb;">
        <p style="margin: 0; font-size: 14px; line-height: 1.5;">
            <strong>{{ $shipment->recipient_name }}</strong><br>
            {{ $shipment->recipient_address }}<br>
            {{ $shipment->recipient_city }}, {{ $shipment->recipient_state }} {{ $shipment->recipient_postal_code }}<br>
            {{ $shipment->recipient_phone }}
        </p>
    </div>
</div>

<div style="margin: 32px 0;">
    <h3 style="color: #374151; font-size: 18px; font-weight: 600; margin: 0 0 16px 0;">
        Package Information
    </h3>
    <div style="background-color: #f9fafb; padding: 16px; border-radius: 8px; border: 1px solid #e5e7eb;">
        <div class="detail-row">
            <span class="detail-label">Weight:</span>
            <span class="detail-value">{{ $shipment->package_weight }} {{ $shipment->weight_unit }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Dimensions:</span>
            <span class="detail-value">{{ $shipment->package_length }} × {{ $shipment->package_width }} × {{ $shipment->package_height }} {{ $shipment->dimension_unit }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Package Type:</span>
            <span class="detail-value">{{ ucfirst($shipment->packaging_type ?? 'Your Packaging') }}</span>
        </div>
        @if($shipment->declared_value)
        <div class="detail-row">
            <span class="detail-label">Declared Value:</span>
            <span class="detail-value">${{ number_format($shipment->declared_value, 2) }} {{ $shipment->currency_code ?? 'USD' }}</span>
        </div>
        @endif
        @if($shipment->package_description)
        <div class="detail-row">
            <span class="detail-label">Description:</span>
            <span class="detail-value">{{ $shipment->package_description }}</span>
        </div>
        @endif
    </div>
</div>

<div style="background-color: #f3f4f6; padding: 20px; border-radius: 8px; margin: 32px 0;">
    <h4 style="color: #374151; font-size: 16px; font-weight: 600; margin: 0 0 12px 0;">
        Admin Actions
    </h4>
    <ul style="margin: 0; padding-left: 20px; color: #6b7280;">
        <li style="margin-bottom: 8px;">Review the attached shipping label</li>
        <li style="margin-bottom: 8px;">Monitor shipment progress using tracking number: {{ $trackingNumber }}</li>
        <li style="margin-bottom: 8px;">Contact customer if any issues arise</li>
        <li style="margin-bottom: 8px;">Update internal records as needed</li>
    </ul>
</div>

<p style="margin: 32px 0 0 0; font-size: 14px; color: #6b7280;">
    <strong>Note:</strong> This is an automated notification for admin review. The customer has also been notified separately.
</p>
@endsection
