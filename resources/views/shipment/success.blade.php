@extends('layout')

@section('title', 'Payment Successful - BagVoyage')

@section('content')
<div class="py-12 bg-gray-50">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Success Header -->
        <div class="bg-white rounded-lg shadow-md p-8 text-center mb-8">
            <div class="mb-6">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Payment Successful!</h1>
                <p class="text-xl text-gray-600">
                    @if($shipment->pickup_type == 'PICKUP')
                        Your shipment has been created and pickup has been scheduled
                    @else
                        Your shipment has been created successfully
                    @endif
                </p>
            </div>

            @if($shipment->tracking_number)
                <div class="bg-indigo-50 rounded-lg p-6 mb-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-2">Tracking Number</h2>
                    <div class="text-2xl font-mono font-bold text-indigo-600 mb-2">
                        {{ $shipment->tracking_number }}
                    </div>
                    <p class="text-sm text-gray-600">
                        Keep this number safe - you can use it to track your package
                    </p>
                </div>
            @endif

            <!-- Action Buttons based on shipment status -->
            <div class="flex flex-col sm:flex-row gap-4 justify-center mb-6">
                @php
                    $fedexResponse = json_decode($shipment->fedex_response, true);
                    $hasLabel = isset($fedexResponse['label_url']) && !empty($fedexResponse['label_url']);
                @endphp

                @if($hasLabel)
                    <a href="{{ route('shipment.label.download', $shipment) }}" class="bg-blue-500 text-white px-6 py-3 rounded-md font-medium hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        Download Label
                    </a>
                @endif

                {{-- @if($shipment->tracking_number)
                    <a href="{{ route('shipment.tracking', $shipment) }}" class="bg-green-500 text-white px-6 py-3 rounded-md font-medium hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                        Track Shipment
                    </a>
                @endif --}}
            </div>

            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('home') }}" class="bg-indigo-600 text-white px-6 py-3 rounded-md font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Ship Another Package
                </a>
                {{-- @if($shipment->tracking_number)
                    <a href="{{ route('shipment.track', $shipment->tracking_number) }}" class="bg-white text-indigo-600 border border-indigo-600 px-6 py-3 rounded-md font-medium hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        Track This Package
                    </a>
                @endif --}}
            </div>
        </div>

        <!-- Shipment Status Information -->
        @if($shipment->pickup_type == 'PICKUP')
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Pickup Information</h2>

                <div class="flex items-center mb-3">
                    <svg class="w-6 h-6 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <h3 class="text-lg font-semibold text-green-800">Pickup Scheduled</h3>
                </div>
                <p class="text-sm text-green-700">
                    Pickup confirmed for {{ $shipment->pickup_date ? \Carbon\Carbon::parse($shipment->pickup_date)->format('F j, Y') : $shipment->preferred_ship_date->format('F j, Y') }}
                    @if($shipment->pickup_confirmation)
                        <br>Confirmation: {{ $shipment->pickup_confirmation }}
                    @endif
                </p>
                <div class="mt-4 p-4 bg-blue-50 rounded-lg">
                    <h4 class="font-medium text-blue-800 mb-2">Pickup Details</h4>
                    <ul class="text-sm text-blue-700 space-y-1">
                        <li><span class="font-medium">Ready Time:</span> {{ $shipment->pickup_ready_time ? \Carbon\Carbon::parse($shipment->pickup_ready_time)->format('g:i A') : '9:00 AM' }}</li>
                        <li><span class="font-medium">Close Time:</span> {{ $shipment->pickup_close_time ? \Carbon\Carbon::parse($shipment->pickup_close_time)->format('g:i A') : '5:00 PM' }}</li>
                        <li><span class="font-medium">Location:</span> {{ $shipment->pickup_address ?: $shipment->sender_address_line }}</li>
                        @if($shipment->pickup_instructions)
                            <li><span class="font-medium">Instructions:</span> {{ $shipment->pickup_instructions }}</li>
                        @endif
                    </ul>
                </div>
            </div>
        @endif

        <!-- Shipment Details -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Shipment Details</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <h3 class="font-medium text-gray-900 mb-3">Shipping Information</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Service:</span>
                            <span class="font-medium">{{ $shipment->selectedRate ? $shipment->selectedRate->getServiceDisplayName() : 'N/A' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Method:</span>
                            <span class="font-medium capitalize">{{ $shipment->pickup_type == 'PICKUP' ? 'FedEx Pickup' : 'Drop-off' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Ship Date:</span>
                            <span class="font-medium">{{ $shipment->preferred_ship_date->format('M j, Y') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Status:</span>
                            <span class="font-medium capitalize text-green-600">{{ ucwords(str_replace('_', ' ', $shipment->status)) }}</span>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="font-medium text-gray-900 mb-3">Contact Information</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Sender:</span>
                            <span class="font-medium">{{ $shipment->sender_full_name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Email:</span>
                            <span class="font-medium">{{ $shipment->sender_email }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Phone:</span>
                            <span class="font-medium">{{ $shipment->sender_phone }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Recipient:</span>
                            <span class="font-medium">{{ $shipment->recipient_name }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 pt-6 border-t border-gray-200">
                <h3 class="font-medium text-gray-900 mb-3">Package Information</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <span class="text-gray-600">Weight:</span>
                        <span class="font-medium ml-1">{{ $shipment->package_weight }} {{ $shipment->weight_unit ?? 'lbs' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-600">Dimensions:</span>
                        <span class="font-medium ml-1">{{ $shipment->package_length }}" × {{ $shipment->package_width }}" × {{ $shipment->package_height }}"</span>
                    </div>
                    <div>
                        <span class="text-gray-600">Volume:</span>
                        <span class="font-medium ml-1">{{ number_format($shipment->getPackageVolume(), 2) }} in³</span>
                    </div>
                    <div>
                        <span class="text-gray-600">Total Cost:</span>
                        <span class="font-medium ml-1">${{ $shipment->selectedRate ? number_format($shipment->selectedRate->total_rate, 2) : 'N/A' }}</span>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="text-gray-600">Contents:</span>
                    <span class="font-medium ml-1">{{ $shipment->package_description }}</span>
                </div>
            </div>
        </div>

        <!-- Next Steps -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">What's Next?</h2>

            <div class="space-y-4">
                @if($shipment->pickup_type == 'PICKUP')
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-6 h-6 bg-indigo-100 rounded-full flex items-center justify-center mr-3 mt-0.5">
                            <span class="text-sm font-medium text-indigo-600">1</span>
                        </div>
                        <div>
                            <h3 class="font-medium text-gray-900">Prepare Your Package</h3>
                            <p class="text-sm text-gray-600">
                                Have your package ready for pickup on {{ $shipment->pickup_date ? \Carbon\Carbon::parse($shipment->pickup_date)->format('M j, Y') : $shipment->preferred_ship_date->format('M j, Y') }} between
                                {{ $shipment->pickup_ready_time ? \Carbon\Carbon::parse($shipment->pickup_ready_time)->format('g:i A') : '9:00 AM' }} and
                                {{ $shipment->pickup_close_time ? \Carbon\Carbon::parse($shipment->pickup_close_time)->format('g:i A') : '5:00 PM' }}.
                            </p>
                        </div>
                    </div>
                @else
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-6 h-6 bg-indigo-100 rounded-full flex items-center justify-center mr-3 mt-0.5">
                            <span class="text-sm font-medium text-indigo-600">1</span>
                        </div>
                        <div>
                            <h3 class="font-medium text-gray-900">Drop-off Label Generated</h3>
                            <p class="text-sm text-gray-600">Print your shipping label and take your package to any FedEx location or drop-off point.</p>
                        </div>
                    </div>
                @endif

                <div class="flex items-start">
                    <div class="flex-shrink-0 w-6 h-6 bg-indigo-100 rounded-full flex items-center justify-center mr-3 mt-0.5">
                        <span class="text-sm font-medium text-indigo-600">2</span>
                    </div>
                    <div>
                        <h3 class="font-medium text-gray-900">Email Confirmation</h3>
                        <p class="text-sm text-gray-600">You'll receive an email confirmation with your shipping label and tracking information at {{ $shipment->sender_email }}.</p>
                    </div>
                </div>

                {{-- <div class="flex items-start">
                    <div class="flex-shrink-0 w-6 h-6 bg-indigo-100 rounded-full flex items-center justify-center mr-3 mt-0.5">
                        <span class="text-sm font-medium text-indigo-600">3</span>
                    </div>
                    <div>
                        <h3 class="font-medium text-gray-900">Track Your Package</h3>
                        <p class="text-sm text-gray-600">Monitor your shipment's progress using the tracking number provided above.</p>
                    </div>
                </div> --}}
            </div>
        </div>
    </div>
</div>
@endsection
