@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-3xl mx-auto bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold mb-6">Schedule FedEx Pickup</h1>

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
            <h2 class="text-lg font-semibold mb-2">Shipment Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-600">Shipment ID</p>
                    <p class="font-medium">{{ $shipment->id }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Tracking Number</p>
                    <p class="font-medium">{{ $shipment->tracking_number }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Status</p>
                    <p class="font-medium">{{ ucfirst($shipment->status) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Delivery Type</p>
                    <p class="font-medium">{{ ucfirst($shipment->delivery_type) }}</p>
                </div>
            </div>
        </div>

        <div class="mb-6 p-4 bg-gray-50 rounded-md">
            <h2 class="text-lg font-semibold mb-2">Pickup Address</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-600">Name</p>
                    <p class="font-medium">{{ $shipment->sender_full_name }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Phone</p>
                    <p class="font-medium">{{ $shipment->sender_phone }}</p>
                </div>
                <div class="col-span-2">
                    <p class="text-sm text-gray-600">Address</p>
                    <p class="font-medium">{{ $shipment->pickup_address }}, {{ $shipment->pickup_city }}, {{ $shipment->pickup_state }} {{ $shipment->pickup_postal_code }}</p>
                </div>
            </div>
        </div>

        <div class="mb-6 p-4 bg-gray-50 rounded-md">
            <h2 class="text-lg font-semibold mb-2">Package Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-600">Weight</p>
                    <p class="font-medium">{{ $shipment->package_weight }} lbs</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Dimensions</p>
                    <p class="font-medium">{{ $shipment->package_length }}" × {{ $shipment->package_width }}" × {{ $shipment->package_height }}"</p>
                </div>
            </div>
        </div>

        @if(isset($availability) && $availability['success'])
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-md">
            <h2 class="text-lg font-semibold mb-2 text-green-800">
                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Pickup Available
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @if($cutoff_time)
                <div>
                    <p class="text-sm text-green-600">Cutoff Time</p>
                    <p class="font-medium text-green-800">{{ $cutoff_time }}</p>
                    <p class="text-xs text-green-600">Latest time to schedule pickup for today</p>
                </div>
                @endif
                @if($access_time)
                <div>
                    <p class="text-sm text-green-600">Access Time</p>
                    <p class="font-medium text-green-800">{{ $access_time }}</p>
                    <p class="text-xs text-green-600">Time needed between ready and close time</p>
                </div>
                @endif
            </div>
            <div class="mt-3">
                <p class="text-sm text-green-700">
                    <strong>Important:</strong> Your package must be ready for pickup no later than the cutoff time,
                    and you must allow the access time between when your package is ready and your business close time.
                </p>
            </div>
        </div>
        @endif

        <form action="{{ route('fedex.pickup.schedule', $shipment) }}" method="POST" class="mt-6">
            @csrf
            <div class="mb-6">
                <label for="pickup_date" class="block text-sm font-medium text-gray-700 mb-1">Pickup Date</label>
                <input type="date" name="pickup_date" id="pickup_date"
                    value="{{ $shipment->preferred_ship_date->format('Y-m-d') }}"
                    min="{{ now()->format('Y-m-d') }}"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                <p class="text-sm text-gray-500 mt-1">FedEx will pick up your package between 9:00 AM and 5:00 PM on the selected date.</p>
            </div>

            <div class="mb-4">
                <div class="flex items-center">
                    <input type="checkbox" name="confirm_pickup" id="confirm_pickup" class="mr-2" required>
                    <label for="confirm_pickup" class="text-sm">I confirm that the package will be ready for pickup at the address above on the selected date</label>
                </div>
            </div>

            <div class="flex justify-between mt-8">
                <a href="{{ route('fedex.shipment.details', $shipment) }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                    Back to Shipment Details
                </a>
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Schedule Pickup
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
