@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-3xl mx-auto bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold mb-6">Tracking Information</h1>

        @if ($errors->any())
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p class="font-bold">Please fix the following errors:</p>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mb-6 p-4 bg-blue-50 rounded-md">
            <h2 class="text-lg font-semibold mb-2">Shipment Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-600">Tracking Number</p>
                    <p class="font-medium">{{ $shipment->tracking_number }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Service Type</p>
                    <p class="font-medium">{{ ucfirst($shipment->delivery_type) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Ship Date</p>
                    <p class="font-medium">{{ $shipment->preferred_ship_date->format('F j, Y') }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Status</p>
                    <p class="font-medium">{{ $trackingInfo['status'] ?? 'Pending' }}</p>
                </div>
                @if(isset($trackingInfo['estimated_delivery']))
                <div>
                    <p class="text-sm text-gray-600">Estimated Delivery</p>
                    <p class="font-medium">{{ $trackingInfo['estimated_delivery'] }}</p>
                </div>
                @endif
                @if(isset($trackingInfo['current_location']))
                <div>
                    <p class="text-sm text-gray-600">Current Location</p>
                    <p class="font-medium">{{ $trackingInfo['current_location'] }}</p>
                </div>
                @endif
            </div>
        </div>

        <div class="mb-6 p-4 bg-gray-50 rounded-md">
            <h2 class="text-lg font-semibold mb-2">Shipment Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-600">From</p>
                    <p class="font-medium">{{ $shipment->pickup_city }}, {{ $shipment->pickup_state }} {{ $shipment->pickup_postal_code }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">To</p>
                    <p class="font-medium">{{ $shipment->recipient_city }}, {{ $shipment->recipient_state }} {{ $shipment->recipient_postal_code }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Weight</p>
                    <p class="font-medium">{{ $shipment->package_weight }} lbs</p>
                </div>
            </div>
        </div>

        @if(isset($trackingInfo['updates']) && count($trackingInfo['updates']) > 0)
            <div class="mb-6">
                <h2 class="text-lg font-semibold mb-4">Tracking History</h2>
                <div class="relative">
                    <div class="absolute left-4 top-0 h-full w-0.5 bg-gray-200"></div>
                    <div class="space-y-6">
                        @foreach($trackingInfo['updates'] as $update)
                            <div class="relative pl-10">
                                <div class="absolute left-2 top-2 h-4 w-4 rounded-full bg-blue-500"></div>
                                <div class="bg-white p-4 rounded-md shadow-sm border border-gray-200">
                                    <p class="text-sm text-gray-500">{{ $update['timestamp']->format('F j, Y - g:i A') }}</p>
                                    <p class="font-medium">{{ $update['status'] }}</p>
                                    <p class="text-sm">{{ $update['location'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @else
            <div class="mb-6 p-4 bg-yellow-50 rounded-md">
                <p class="text-sm text-yellow-700">No tracking updates available yet. Please check back later.</p>
            </div>
        @endif

        <div class="mt-8">
            <a href="{{ route('fedex.shipment.details', $shipment) }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                Back to Shipment Details
            </a>
        </div>
    </div>
</div>
@endsection
