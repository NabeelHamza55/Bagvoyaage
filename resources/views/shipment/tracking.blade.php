@extends('layout')

@section('title', 'Track Shipment - BagVoyage')

@section('content')
<div class="py-12 bg-gray-50">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Shipment Tracking</h1>
            <p class="text-gray-600">
                Tracking Number: <span class="font-semibold text-indigo-600">{{ $shipment->tracking_number }}</span>
            </p>
        </div>

        @if (isset($trackingInfo))
            <!-- Tracking Status -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Current Status</h2>

                <div class="flex items-center mb-4">
                    <div class="flex-shrink-0 w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">{{ $trackingInfo['status'] ?? 'In Transit' }}</h3>
                        @if(isset($trackingInfo['estimated_delivery']))
                            <p class="text-sm text-gray-600">Estimated Delivery: {{ $trackingInfo['estimated_delivery'] }}</p>
                        @endif
                        @if(isset($trackingInfo['current_location']))
                            <p class="text-sm text-gray-600">Current Location: {{ $trackingInfo['current_location'] }}</p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Tracking Updates -->
            @if(isset($trackingInfo['updates']) && count($trackingInfo['updates']) > 0)
                <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Tracking History</h2>

                    <div class="space-y-4">
                        @foreach($trackingInfo['updates'] as $update)
                            <div class="flex items-start">
                                <div class="flex-shrink-0 w-3 h-3 bg-indigo-600 rounded-full mt-2 mr-4"></div>
                                <div class="flex-1">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h3 class="font-medium text-gray-900">{{ $update['status'] ?? 'Status Update' }}</h3>
                                            @if(isset($update['location']))
                                                <p class="text-sm text-gray-600">{{ $update['location'] }}</p>
                                            @endif
                                            @if(isset($update['description']))
                                                <p class="text-sm text-gray-600">{{ $update['description'] }}</p>
                                            @endif
                                        </div>
                                        @if(isset($update['timestamp']))
                                            <span class="text-sm text-gray-500">{{ $update['timestamp'] }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @else
            <!-- No tracking info available -->
            <div class="bg-white rounded-lg shadow-md p-8 text-center">
                <div class="mb-6">
                    <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No Tracking Information Available</h3>
                <p class="text-gray-600 mb-6">
                    Tracking information may not be available yet. Please check back later or contact customer service.
                </p>
            </div>
        @endif

        <!-- Shipment Details -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Shipment Details</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="font-medium text-gray-900 mb-2">From</h3>
                    <p class="text-sm text-gray-600">
                        {{ $shipment->sender_full_name }}<br>
                        @if($shipment->pickup_address)
                            {{ $shipment->pickup_address }}<br>
                            {{ $shipment->pickup_city }}, {{ $shipment->pickup_state }} {{ $shipment->pickup_postal_code }}
                        @else
                            {{ $shipment->origin_state }}
                        @endif
                    </p>
                </div>
                <div>
                    <h3 class="font-medium text-gray-900 mb-2">To</h3>
                    <p class="text-sm text-gray-600">
                        {{ $shipment->recipient_name }}<br>
                        {{ $shipment->recipient_address }}<br>
                        {{ $shipment->recipient_city }}, {{ $shipment->recipient_state }} {{ $shipment->recipient_postal_code }}
                    </p>
                </div>
            </div>

            <div class="mt-6 pt-6 border-t border-gray-200">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <span class="text-gray-600">Service:</span>
                        <span class="font-medium ml-1">{{ $shipment->selectedRate ? $shipment->selectedRate->getServiceDisplayName() : ucfirst($shipment->delivery_type) }}</span>
                    </div>
                    <div>
                        <span class="text-gray-600">Weight:</span>
                        <span class="font-medium ml-1">{{ $shipment->package_weight }} lbs</span>
                    </div>
                    <div>
                        <span class="text-gray-600">Ship Date:</span>
                        <span class="font-medium ml-1">{{ $shipment->preferred_ship_date->format('M j, Y') }}</span>
                    </div>
                    <div>
                        <span class="text-gray-600">Method:</span>
                        <span class="font-medium ml-1 capitalize">{{ $shipment->delivery_method }}</span>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-between">
                <a href="{{ route('shipment.success', $shipment) }}" class="bg-gray-500 text-white px-4 py-2 rounded-md font-medium hover:bg-gray-600">
                    Back to Shipment Details
                </a>

                <div class="flex space-x-3">
                    @if($shipment->delivery_method === 'pickup' && !$shipment->pickup_scheduled)
                        <a href="{{ route('shipment.pickup.form', $shipment) }}" class="bg-yellow-500 text-white px-4 py-2 rounded-md font-medium hover:bg-yellow-600">
                            Schedule Pickup
                        </a>
                    @endif

                    @php
                        $fedexResponse = json_decode($shipment->fedex_response, true);
                        $hasLabel = isset($fedexResponse['label_url']) && !empty($fedexResponse['label_url']);
                    @endphp

                    @if($hasLabel)
                        <a href="{{ route('shipment.label.download', $shipment) }}" class="bg-blue-500 text-white px-4 py-2 rounded-md font-medium hover:bg-blue-600">
                            Download Label
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
