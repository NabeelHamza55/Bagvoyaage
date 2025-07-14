@extends('emails.layout')

@section('content')
<div style="text-align: center; margin-bottom: 32px;">
    <div class="status-badge status-confirmed">Shipping Update</div>
</div>

<h2 style="color: #1f2937; font-size: 24px; font-weight: 700; margin: 0 0 16px 0;">
    Hello {{ $customerName }},
</h2>

<p style="margin: 0 0 24px 0; font-size: 16px; line-height: 1.6;">
    We have an update regarding your shipment. Here are the current details:
</p>

<div class="shipping-details">
    <h3 style="margin: 0 0 16px 0; color: #374151; font-size: 18px; font-weight: 600;">
        Shipment Information
    </h3>

    <div class="detail-row">
        <span class="detail-label">Tracking Number:</span>
        <span class="detail-value tracking-number">{{ $trackingNumber }}</span>
    </div>

    <div class="detail-row">
        <span class="detail-label">Status:</span>
        <span class="detail-value">{{ ucfirst($shipment->status) }}</span>
    </div>

    <div class="detail-row">
        <span class="detail-label">Service:</span>
        <span class="detail-value">{{ $shipment->service_type ?? 'FedEx International' }}</span>
    </div>

    <div class="detail-row">
        <span class="detail-label">Origin:</span>
        <span class="detail-value">{{ $shipment->origin_country }}</span>
    </div>

    <div class="detail-row">
        <span class="detail-label">Destination:</span>
        <span class="detail-value">{{ $shipment->destination_country }}</span>
    </div>
</div>

<div style="text-align: center; margin: 40px 0;">
    <a href="{{ $trackingUrl }}" class="btn">
        Track Your Package
    </a>
</div>

<div style="background-color: #f3f4f6; padding: 20px; border-radius: 8px; margin: 32px 0;">
    <h4 style="color: #374151; font-size: 16px; font-weight: 600; margin: 0 0 12px 0;">
        Need Help?
    </h4>
    <p style="margin: 0; color: #6b7280; font-size: 14px;">
        If you have any questions about your shipment, please don't hesitate to contact our customer support team.
        We're here to help ensure your package reaches its destination safely and on time.
    </p>
</div>

<p style="margin: 32px 0 0 0; font-size: 14px; color: #6b7280;">
    Thank you for choosing BagVoyage for your international shipping needs.
</p>
@endsection
