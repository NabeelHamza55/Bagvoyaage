@extends('layout')

@section('title', 'Checkout - BagVoyage')

@section('content')
<div class="py-12 bg-gray-50">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Checkout</h1>
            <p class="text-gray-600">Review your shipment details and complete payment</p>
        </div>

        @if (session('success'))
            <div class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status">
                {{ session('success') }}
            </div>
        @endif

        @if (empty(config('services.paypal.client_id')) || config('services.paypal.client_id') === 'YOUR_PAYPAL_CLIENT_ID')
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-8">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-700">
                            <strong>Configuration Required:</strong> PayPal API credentials are not configured. Please update your .env file with valid PayPal credentials to enable payments.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Order Summary -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Shipment Details -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Shipment Details</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h3 class="font-medium text-gray-900 mb-2">From</h3>
                            <p class="text-sm text-gray-600">
                                {{ $shipment->sender_full_name }}<br>
                                {{ $states[$shipment->origin_state] }}<br>
                                {{ $shipment->sender_email }}<br>
                                {{ $shipment->sender_phone }}
                            </p>
                        </div>
                        <div>
                            <h3 class="font-medium text-gray-900 mb-2">To</h3>
                            <p class="text-sm text-gray-600">
                                {{ $shipment->recipient_name }}<br>
                                {{ $shipment->recipient_address }}<br>
                                {{ $shipment->recipient_city }}, {{ $states[$shipment->recipient_state] }} {{ $shipment->recipient_postal_code }}<br>
                                {{ $shipment->recipient_phone }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <h3 class="font-medium text-gray-900 mb-2">Package Information</h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                            <div>
                                <span class="text-gray-600">Weight:</span>
                                <span class="font-medium ml-1">{{ $shipment->package_weight }} lbs</span>
                            </div>
                            <div>
                                <span class="text-gray-600">Dimensions:</span>
                                <span class="font-medium ml-1">{{ $shipment->package_length }}" × {{ $shipment->package_width }}" × {{ $shipment->package_height }}"</span>
                            </div>
                            <div>
                                <span class="text-gray-600">Method:</span>
                                <span class="font-medium ml-1 capitalize">{{ $shipment->delivery_method }}</span>
                            </div>
                            <div>
                                <span class="text-gray-600">{{ $shipment->pickup_type === 'PICKUP' ? 'Ship / pickup date:' : 'Ship date:' }}</span>
                                <span class="font-medium ml-1">{{ $shipment->preferred_ship_date->format('M j, Y') }}</span>
                            </div>
                            @if($shipment->pickup_type === 'PICKUP' && $shipment->pickup_date)
                                @php
                                    $fmtP = function ($t) {
                                        if (!$t) return '—';
                                        $t = strlen(trim($t)) === 5 ? trim($t).':00' : trim($t);
                                        try {
                                            return \Carbon\Carbon::createFromFormat('H:i:s', $t)->format('g:i A');
                                        } catch (\Throwable) {
                                            return $t;
                                        }
                                    };
                                @endphp
                                <div class="md:col-span-2 text-sm">
                                    <span class="text-gray-600">FedEx pickup:</span>
                                    <span class="font-medium ml-1">{{ $shipment->pickup_date->format('M j, Y') }}</span>
                                    @if($shipment->pickup_ready_time && $shipment->pickup_close_time)
                                        <span class="text-gray-600"> · Ready </span>
                                        <span class="font-medium">{{ $fmtP($shipment->pickup_ready_time) }}</span>
                                        <span class="text-gray-600"> — latest </span>
                                        <span class="font-medium">{{ $fmtP($shipment->pickup_close_time) }}</span>
                                    @endif
                                    @if($shipment->pickup_address)
                                        <span class="text-gray-500"> — {{ $shipment->pickup_city }}, {{ $shipment->pickup_postal_code }}</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                        <div class="mt-3">
                            <span class="text-gray-600">Contents:</span>
                            <span class="font-medium ml-1">{{ $shipment->package_description }}</span>
                        </div>
                    </div>
                </div>

                <!-- Selected Shipping Rate -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Selected Service</h2>

                    <div class="flex justify-between items-start p-4 bg-indigo-50 rounded-lg">
                        <div>
                            <h3 class="font-semibold text-gray-900">{{ $selectedRate->getServiceDisplayName() }}</h3>
                            @if($selectedRate->transit_days)
                                <p class="text-sm text-gray-600 mt-1">
                                    Estimated delivery: {{ $selectedRate->transit_days }} business day(s)
                                </p>
                            @endif
                            @if($selectedRate->delivery_date)
                                <p class="text-sm text-gray-600">
                                    Expected delivery: {{ $selectedRate->delivery_date->format('M j, Y') }}
                                </p>
                            @endif
                        </div>
                        <div class="text-right">
                            <div class="text-2xl font-bold text-indigo-600">
                                ${{ number_format($selectedRate->total_rate, 2) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Method -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Payment Method</h2>

                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <img src="https://www.paypalobjects.com/webstatic/mktg/Logo/pp-logo-100px.png" alt="PayPal" class="h-8 mr-3">
                            <div>
                                <h3 class="font-medium text-gray-900">PayPal</h3>
                                <p class="text-sm text-gray-600">Secure payment with PayPal</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Summary -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-md p-6 sticky top-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Order Summary</h2>

                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Base shipping rate:</span>
                            <span class="font-medium">${{ number_format($selectedRate->base_rate, 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Handling fee (10%):</span>
                            <span class="font-medium">${{ number_format($selectedRate->handling_fee, 2) }}</span>
                        </div>
                        <div class="border-t border-gray-200 pt-3">
                            <div class="flex justify-between text-lg font-semibold">
                                <span>Total:</span>
                                <span class="text-indigo-600">${{ number_format($selectedRate->total_rate, 2) }}</span>
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('shipment.payment', $shipment) }}" class="mt-6">
                        @csrf

                        <button
                            type="submit"
                            class="w-full bg-indigo-600 text-white px-6 py-3 rounded-md font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors duration-200"
                            {{ (empty(config('services.paypal.client_id')) || config('services.paypal.client_id') === 'YOUR_PAYPAL_CLIENT_ID') ? 'disabled' : '' }}
                        >
                            Pay with PayPal
                        </button>

                        @if (empty(config('services.paypal.client_id')) || config('services.paypal.client_id') === 'YOUR_PAYPAL_CLIENT_ID')
                            <p class="mt-2 text-sm text-red-600">
                                PayPal payment is currently unavailable. Please configure your PayPal API credentials.
                            </p>
                        @endif
                    </form>

                    <div class="mt-4 text-center">
                        <p class="text-xs text-gray-500">
                            By continuing, you agree to our Terms of Service and Privacy Policy.
                            Your payment is secured by PayPal.
                        </p>
                    </div>

                    <div class="mt-4 pt-4 border-t border-gray-200 space-y-2">
                        <a href="{{ route('shipment.rates', $shipment) }}" class="block text-indigo-600 hover:text-indigo-700 text-sm font-medium">
                            ← Back to rates &amp; delivery (change service or pickup)
                        </a>
                        @if($shipment->pickup_type === 'PICKUP')
                            <a href="{{ route('shipment.pickup-details', $shipment) }}" class="block text-gray-600 hover:text-gray-800 text-sm">
                                Edit pickup address &amp; times
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Add loading state to payment button
    document.addEventListener('DOMContentLoaded', function() {
        const paymentForm = document.querySelector('form[action*="payment"]');
        const paymentButton = paymentForm.querySelector('button[type="submit"]');

        paymentForm.addEventListener('submit', function() {
            paymentButton.innerHTML = `
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Processing Payment...
            `;
            paymentButton.disabled = true;
        });
    });
</script>
@endpush
@endsection
