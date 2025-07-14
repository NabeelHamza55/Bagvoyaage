@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <!-- Success Header -->
        <div class="bg-white rounded-lg shadow-md p-8 text-center mb-8">
            <div class="mb-6">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Shipment Created Successfully!</h1>
                <p class="text-xl text-gray-600">Your package is ready for shipping</p>
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
        </div>

        @if (session('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p>{{ session('success') }}</p>
            </div>
        @endif

        @if (session('warning'))
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6" role="alert">
                <p>{{ session('warning') }}</p>
            </div>
        @endif

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

        <!-- Action Buttons -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Next Steps</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @if($shipment->delivery_method === 'pickup' && !$shipment->pickup_scheduled)
                    <div class="p-6 border-2 border-yellow-200 rounded-lg bg-yellow-50">
                        <div class="flex items-center mb-3">
                            <svg class="w-6 h-6 text-yellow-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <h3 class="text-lg font-semibold text-yellow-800">Schedule Pickup</h3>
                        </div>
                        <p class="text-sm text-yellow-700 mb-4">FedEx will pick up your package from your location on the scheduled date.</p>
                        <a href="{{ route('fedex.pickup.form', $shipment) }}" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded inline-block">
                            Schedule Pickup Now
                        </a>
                    </div>
                @elseif($shipment->pickup_scheduled)
                    <div class="p-6 border-2 border-green-200 rounded-lg bg-green-50">
                        <div class="flex items-center mb-3">
                            <svg class="w-6 h-6 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <h3 class="text-lg font-semibold text-green-800">Pickup Scheduled</h3>
                        </div>
                        <p class="text-sm text-green-700">
                            Pickup confirmed for {{ $shipment->preferred_ship_date->format('F j, Y') }}
                            @if($shipment->pickup_confirmation)
                                <br>Confirmation: {{ $shipment->pickup_confirmation }}
                            @endif
                        </p>
                    </div>
                @endif

                @php
                    $fedexResponse = json_decode($shipment->fedex_response, true);
                    $hasLabel = isset($fedexResponse['label_url']) && !empty($fedexResponse['label_url']);
                @endphp

                @if($hasLabel)
                    <div class="p-6 border-2 border-blue-200 rounded-lg bg-blue-50">
                        <div class="flex items-center mb-3">
                            <svg class="w-6 h-6 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <h3 class="text-lg font-semibold text-blue-800">Shipping Label</h3>
                        </div>
                        <p class="text-sm text-blue-700 mb-4">Download and print your shipping label.</p>
                        <a href="{{ route('fedex.label.download', $shipment) }}" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded inline-block">
                            Download Label
                        </a>
                    </div>
                @endif

                @if($shipment->tracking_number)
                    <div class="p-6 border-2 border-indigo-200 rounded-lg bg-indigo-50">
                        <div class="flex items-center mb-3">
                            <svg class="w-6 h-6 text-indigo-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <h3 class="text-lg font-semibold text-indigo-800">Track Package</h3>
                        </div>
                        <p class="text-sm text-indigo-700 mb-4">Monitor your shipment's progress in real-time.</p>
                        <a href="{{ route('fedex.tracking', $shipment) }}" class="bg-indigo-500 hover:bg-indigo-600 text-white font-bold py-2 px-4 rounded inline-block">
                            Track Now
                        </a>
                    </div>
                @endif
            </div>
        </div>

        <!-- Shipment Details -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Shipment Details</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <h3 class="font-medium text-gray-900 mb-3">Shipping Information</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Shipment ID:</span>
                            <span class="font-medium">{{ $shipment->id }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Service:</span>
                            <span class="font-medium">{{ $shipment->selectedRate ? $shipment->selectedRate->getServiceDisplayName() : ucfirst($shipment->delivery_type) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Method:</span>
                            <span class="font-medium capitalize">{{ $shipment->delivery_method }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Ship Date:</span>
                            <span class="font-medium">{{ $shipment->preferred_ship_date->format('M j, Y') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Status:</span>
                            <span class="font-medium capitalize text-green-600">{{ str_replace('_', ' ', $shipment->status) }}</span>
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
                        <span class="font-medium ml-1">{{ $shipment->package_weight }} lbs</span>
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

        <!-- Navigation -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('home') }}" class="bg-indigo-600 text-white px-6 py-3 rounded-md font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 text-center">
                    Ship Another Package
                </a>
                @if($shipment->tracking_number)
                    <a href="{{ route('shipment.track', $shipment->tracking_number) }}" class="bg-white text-indigo-600 border border-indigo-600 px-6 py-3 rounded-md font-medium hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 text-center">
                        External Tracking
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
