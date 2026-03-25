@extends('emails.layout')

@section('content')
<div style="text-align: center; margin-bottom: 32px;">
    <div class="status-badge status-confirmed">Pickup Scheduled</div>
</div>

<h2 style="color: #1f2937; font-size: 24px; font-weight: 700; margin: 0 0 16px 0;">
    Hello {{ $shipment->sender_full_name }},
</h2>

<p style="margin: 0 0 24px 0; font-size: 16px; line-height: 1.6;">
    Great news! Your FedEx pickup has been successfully scheduled. A FedEx driver will arrive at your location to collect your package.
</p>

<div class="shipping-details">
    <h3 style="margin: 0 0 16px 0; color: #374151; font-size: 18px; font-weight: 600;">
        Pickup Details
    </h3>

    <div class="detail-row">
        <span class="detail-label">Confirmation Number:</span>
        <span class="detail-value tracking-number">{{ $shipment->pickup_confirmation }}</span>
    </div>

    <div class="detail-row">
        <span class="detail-label">Tracking Number:</span>
        <span class="detail-value tracking-number">{{ $shipment->tracking_number }}</span>
    </div>

    <div class="detail-row">
        <span class="detail-label">Scheduled Date:</span>
        <span class="detail-value">{{ \Carbon\Carbon::parse($shipment->pickup_date ?? $shipment->preferred_ship_date)->format('l, F j, Y') }}</span>
    </div>

    <div class="detail-row">
        <span class="detail-label">Pickup Time:</span>
        <span class="detail-value">{{ ucfirst($shipment->pickup_time_slot ?? 'Morning') }} (8:00 AM - 12:00 PM)</span>
    </div>

    <div class="detail-row">
        <span class="detail-label">Service Type:</span>
        <span class="detail-value">{{ str_replace('_', ' ', $shipment->selectedRate->service_type ?? 'FedEx Ground') }}</span>
    </div>
</div>

<div style="margin: 32px 0;">
    <h3 style="color: #374151; font-size: 18px; font-weight: 600; margin: 0 0 16px 0;">
        Pickup Address
    </h3>
    <div style="background-color: #f9fafb; padding: 16px; border-radius: 8px; border: 1px solid #e5e7eb;">
        <p style="margin: 0; font-size: 14px; line-height: 1.5;">
            <strong>{{ $shipment->sender_full_name }}</strong><br>
            {{ $shipment->sender_address_line }}<br>
            {{ $shipment->sender_city }}, {{ $shipment->sender_state }} {{ $shipment->sender_zipcode }}<br>
            {{ $shipment->origin_country ?? 'US' }}<br>
            {{ $shipment->sender_phone }}<br>
            {{ $shipment->sender_email }}
        </p>
    </div>
</div>

<div style="background-color: #eff6ff; padding: 20px; border-radius: 8px; margin: 32px 0; border-left: 4px solid #3b82f6;">
    <h4 style="color: #1e40af; font-size: 16px; font-weight: 600; margin: 0 0 12px 0;">
        📦 Important Pickup Instructions
    </h4>
    <ul style="margin: 0; padding-left: 20px; color: #1e3a8a;">
        <li style="margin-bottom: 8px;">Have your package ready and properly labeled</li>
        <li style="margin-bottom: 8px;">Ensure the shipping label is clearly visible on the package</li>
        <li style="margin-bottom: 8px;">Be available at the pickup location during the scheduled time window</li>
        <li style="margin-bottom: 8px;">If you need to change or cancel the pickup, contact FedEx at 1-800-463-3339</li>
    </ul>
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
            <span class="detail-label">Bag Type:</span>
            <span class="detail-value">{{ ucfirst($shipment->bag_type) }} ({{ $shipment->number_of_bags }} bag{{ $shipment->number_of_bags > 1 ? 's' : '' }})</span>
        </div>
        @if($shipment->declared_value)
        <div class="detail-row">
            <span class="detail-label">Declared Value:</span>
            <span class="detail-value">${{ number_format($shipment->declared_value, 2) }} {{ $shipment->currency_code ?? 'USD' }}</span>
        </div>
        @endif
    </div>
</div>

<div style="text-align: center; margin: 40px 0;">
    <a href="{{ route('shipment.success', $shipment->id) }}" class="btn">
        View Shipment Details
    </a>
</div>

<div style="background-color: #f3f4f6; padding: 20px; border-radius: 8px; margin: 32px 0;">
    <h4 style="color: #374151; font-size: 16px; font-weight: 600; margin: 0 0 12px 0;">
        What happens next?
    </h4>
    <ul style="margin: 0; padding-left: 20px; color: #6b7280;">
        <li style="margin-bottom: 8px;">FedEx driver will arrive during your scheduled time window</li>
        <li style="margin-bottom: 8px;">Your package will be scanned and collected</li>
        <li style="margin-bottom: 8px;">You'll receive tracking updates as your package moves</li>
        <li style="margin-bottom: 8px;">Estimated delivery: {{ \Carbon\Carbon::parse($shipment->preferred_ship_date)->addDays(3)->format('F j, Y') }}</li>
    </ul>
</div>

<p style="margin: 32px 0 0 0; font-size: 14px; color: #6b7280;">
    <strong>Need to reschedule or cancel?</strong> Contact FedEx directly at 1-800-463-3339 with your confirmation number: <strong>{{ $shipment->pickup_confirmation }}</strong>
</p>

<p style="margin: 16px 0 0 0; font-size: 14px; color: #6b7280;">
    <strong>Questions about your shipment?</strong> Contact us at support@bagvoyaage.org or reply to this email.
</p>
@endsection
