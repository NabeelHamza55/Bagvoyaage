@extends('emails.layout')

@section('content')
<div style="text-align: center; margin-bottom: 32px;">
    <div class="status-badge status-confirmed">Order Confirmed</div>
</div>

<h2 style="color: #1f2937; font-size: 24px; font-weight: 700; margin: 0 0 16px 0;">
    Hello {{ $customerName }},
</h2>

<p style="margin: 0 0 24px 0; font-size: 16px; line-height: 1.6;">
    Great news! Your international shipping order has been confirmed and is being processed. We'll notify you as soon as your shipping label is ready and when your package is picked up.
</p>

<div class="shipping-details">
    <h3 style="margin: 0 0 16px 0; color: #374151; font-size: 18px; font-weight: 600;">
        Shipment Details
    </h3>

    <div class="detail-row">
        <span class="detail-label">Tracking Number:</span>
        <span class="detail-value tracking-number">{{ $trackingNumber }}</span>
    </div>

    <div class="detail-row">
        <span class="detail-label">Origin:</span>
        <span class="detail-value">{{ $shipment->origin_country }}</span>
    </div>

    <div class="detail-row">
        <span class="detail-label">Destination:</span>
        <span class="detail-value">{{ $shipment->destination_country }}</span>
    </div>

    <div class="detail-row">
        <span class="detail-label">Service Type:</span>
        <span class="detail-value">{{ $shipment->service_type ?? 'FedEx International' }}</span>
    </div>

    <div class="detail-row">
        <span class="detail-label">Delivery Option:</span>
        <span class="detail-value">{{ ucfirst($shipment->delivery_option) }}</span>
    </div>

    @if($shipment->estimated_delivery_date)
    <div class="detail-row">
        <span class="detail-label">Estimated Delivery:</span>
        <span class="detail-value">{{ \Carbon\Carbon::parse($shipment->estimated_delivery_date)->format('F j, Y') }}</span>
    </div>
    @endif
</div>

<div style="margin: 32px 0;">
    <h3 style="color: #374151; font-size: 18px; font-weight: 600; margin: 0 0 16px 0;">
        Sender Information
    </h3>
    <div style="background-color: #f9fafb; padding: 16px; border-radius: 8px; border: 1px solid #e5e7eb;">
        <p style="margin: 0; font-size: 14px; line-height: 1.5;">
            <strong>{{ $shipment->sender_name }}</strong><br>
            {{ $shipment->sender_company ? $shipment->sender_company . '<br>' : '' }}
            {{ $shipment->sender_address }}<br>
            {{ $shipment->sender_city }}, {{ $shipment->sender_state }} {{ $shipment->sender_postal_code }}<br>
            {{ $shipment->sender_country }}<br>
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
            {{ $shipment->recipient_company ? $shipment->recipient_company . '<br>' : '' }}
            {{ $shipment->recipient_address }}<br>
            {{ $shipment->recipient_city }}, {{ $shipment->recipient_state }} {{ $shipment->recipient_postal_code }}<br>
            {{ $shipment->recipient_country }}<br>
            {{ $shipment->recipient_phone }}<br>
            {{ $shipment->recipient_email }}
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
            <span class="detail-value">{{ $shipment->weight }} {{ $shipment->weight_unit }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Dimensions:</span>
            <span class="detail-value">{{ $shipment->length }} × {{ $shipment->width }} × {{ $shipment->height }} {{ $shipment->dimension_unit }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Package Type:</span>
            <span class="detail-value">{{ ucfirst($shipment->package_type) }}</span>
        </div>
        @if($shipment->declared_value)
        <div class="detail-row">
            <span class="detail-label">Declared Value:</span>
            <span class="detail-value">${{ number_format($shipment->declared_value, 2) }} {{ $shipment->currency ?? 'USD' }}</span>
        </div>
        @endif
        @if($shipment->contents_description)
        <div class="detail-row">
            <span class="detail-label">Contents:</span>
            <span class="detail-value">{{ $shipment->contents_description }}</span>
        </div>
        @endif
    </div>
</div>

<div style="text-align: center; margin: 40px 0;">
    <a href="{{ $trackingUrl }}" class="btn">
        Track Your Package
    </a>
</div>

<div style="background-color: #f3f4f6; padding: 20px; border-radius: 8px; margin: 32px 0;">
    <h4 style="color: #374151; font-size: 16px; font-weight: 600; margin: 0 0 12px 0;">
        What happens next?
    </h4>
    <ul style="margin: 0; padding-left: 20px; color: #6b7280;">
        <li style="margin-bottom: 8px;">We'll process your shipment and generate the shipping label</li>
        <li style="margin-bottom: 8px;">You'll receive an email with the shipping label attached</li>
        @if($shipment->delivery_option === 'pickup')
        <li style="margin-bottom: 8px;">FedEx will schedule a pickup at your specified address</li>
        @else
        <li style="margin-bottom: 8px;">You can drop off your package at any FedEx location</li>
        @endif
        <li style="margin-bottom: 8px;">We'll send you tracking updates as your package moves</li>
    </ul>
</div>

<p style="margin: 32px 0 0 0; font-size: 14px; color: #6b7280;">
    <strong>Need help?</strong> Our customer support team is available 24/7 to assist you.
    Reply to this email or contact us at support@bagvoyage.com.
</p>
@endsection
