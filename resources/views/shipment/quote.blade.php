@extends('layout')

@section('title', 'Shipping Rates - BagVoyage')

@section('content')
<div class="py-12 bg-gray-50">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Shipping Rates</h1>
            <p class="text-gray-600">
                From: <span class="font-semibold">{{ $states[$shipment->origin_state] }}</span> →
                To: <span class="font-semibold">{{ $states[$shipment->destination_state] }}</span>
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Shipment Summary -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-md p-6 sticky top-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Shipment Summary</h2>

                    <div class="space-y-6">
                        <div>
                            <h3 class="font-medium text-gray-900 mb-2">Package Details</h3>
                            <div class="text-sm text-gray-600">
                                <p>Weight: {{ $shipment->package_weight }} lbs</p>
                                <p>Dimensions: {{ $shipment->package_length }}" × {{ $shipment->package_width }}" × {{ $shipment->package_height }}"</p>
                                <p class="mt-2">{{ $shipment->package_description }}</p>
                            </div>
                        </div>

                        <div>
                            <h3 class="font-medium text-gray-900 mb-2">Delivery Method</h3>
                            <p class="text-sm text-gray-600 capitalize">{{ $shipment->delivery_method }}</p>
                            @if($shipment->delivery_method === 'pickup')
                                <div class="mt-2 text-sm text-gray-600">
                                    <p>{{ $shipment->pickup_address }}</p>
                                    <p>{{ $shipment->pickup_city }}, {{ $states[$shipment->pickup_state] }} {{ $shipment->pickup_postal_code }}</p>
                                </div>
                            @endif
                        </div>

                        <div>
                            <h3 class="font-medium text-gray-900 mb-2">Preferred Ship Date</h3>
                            <p class="text-sm text-gray-600">{{ \Carbon\Carbon::parse($shipment->preferred_ship_date)->format('F j, Y') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Shipping Rates -->
            <div class="lg:col-span-2">
                @if($rates && count($rates) > 0)
                    <!-- Delivery Type Preference Header -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-blue-800">
                                        Available FedEx Services
                                    </h3>
                                    <p class="text-sm text-blue-600 mt-1">
                                        Only the main FedEx service types are available. You can select any option below.
                                    </p>
                                </div>
                            </div>
                            <div class="flex-shrink-0">
                                <button
                                    type="button"
                                    id="toggleAllRates"
                                    class="text-sm text-blue-600 hover:text-blue-800 font-medium underline"
                                >
                                    Show All Rates
                                </button>
                            </div>
                        </div>
                    </div>

                    <form method="GET" action="{{ route('shipment.checkout', $shipment) }}">
                        <!-- Group rates by category and filter by delivery type preference -->
                        @php
                            $ratesCollection = collect($rates);
                            // No longer using delivery_type - rates are filtered at API level

                            // Rates are already filtered at the API level
                            $filteredRates = $ratesCollection;

                            $groundRates = $filteredRates->filter(function($rate) {
                                return strpos($rate->service_type, 'GROUND') !== false;
                            });

                            $expressRates = $filteredRates->filter(function($rate) {
                                return strpos($rate->service_type, 'EXPRESS') !== false ||
                                       strpos($rate->service_type, '2_DAY') !== false ||
                                       strpos($rate->service_type, '3_DAY') !== false;
                            });

                            $overnightRates = $filteredRates->filter(function($rate) {
                                return strpos($rate->service_type, 'OVERNIGHT') !== false ||
                                       strpos($rate->service_type, '1_DAY') !== false ||
                                       strpos($rate->service_type, 'PRIORITY') !== false ||
                                       strpos($rate->service_type, 'FIRST') !== false;
                            });

                            $otherRates = $filteredRates->filter(function($rate) {
                                return strpos($rate->service_type, 'GROUND') === false &&
                                       strpos($rate->service_type, 'EXPRESS') === false &&
                                       strpos($rate->service_type, '2_DAY') === false &&
                                       strpos($rate->service_type, '3_DAY') === false &&
                                       strpos($rate->service_type, 'OVERNIGHT') === false &&
                                       strpos($rate->service_type, '1_DAY') === false &&
                                       strpos($rate->service_type, 'PRIORITY') === false &&
                                       strpos($rate->service_type, 'FIRST') === false;
                            });
                        @endphp

                        <!-- Ground Services -->
                        @if(count($groundRates) > 0)
                            <div class="mb-6 rate-section">
                                <h3 class="text-lg font-semibold text-gray-900 mb-3 border-b pb-2">Ground Services</h3>
                                <div class="space-y-3">
                                    @foreach($groundRates as $rate)
                                        <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-200">
                                            <label class="flex items-center p-4 cursor-pointer hover:bg-gray-50">
                                                <input
                                                    type="radio"
                                                    name="selected_rate"
                                                    value="{{ $rate->id }}"
                                                    class="mr-4 text-indigo-600 w-4 h-4"
                                                    required
                                                >
                                                <div class="flex-1">
                                                    <div class="flex justify-between items-start">
                                                        <div>
                                                            <h3 class="text-lg font-semibold text-gray-900">
                                                                {{ $rate->getServiceDisplayName() }}
                                                            </h3>
                                                            <p class="text-sm text-gray-600 mt-1">
                                                                @if($rate->transit_days)
                                                                    Estimated delivery: {{ $rate->transit_days }} business day(s)
                                                                @endif
                                                            </p>
                                                        </div>
                                                        <div class="text-right">
                                                            <div class="text-2xl font-bold text-indigo-600">
                                                                ${{ number_format($rate->total_rate, 2) }}
                                                            </div>
                                                            <div class="text-xs text-gray-500 mt-1">
                                                                Base: ${{ number_format($rate->base_rate, 2) }}<br>
                                                                Handling: ${{ number_format($rate->handling_fee, 2) }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- Express Services -->
                        @if(count($expressRates) > 0)
                            <div class="mb-6 rate-section">
                                <h3 class="text-lg font-semibold text-gray-900 mb-3 border-b pb-2">Express Services</h3>
                                <div class="space-y-3">
                                    @foreach($expressRates as $rate)
                                        <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-200">
                                            <label class="flex items-center p-4 cursor-pointer hover:bg-gray-50">
                                                <input
                                                    type="radio"
                                                    name="selected_rate"
                                                    value="{{ $rate->id }}"
                                                    class="mr-4 text-indigo-600 w-4 h-4"
                                                    required
                                                >
                                                <div class="flex-1">
                                                    <div class="flex justify-between items-start">
                                                        <div>
                                                            <h3 class="text-lg font-semibold text-gray-900">
                                                                {{ $rate->getServiceDisplayName() }}
                                                            </h3>
                                                            <p class="text-sm text-gray-600 mt-1">
                                                                @if($rate->transit_days)
                                                                    Estimated delivery: {{ $rate->transit_days }} business day(s)
                                                                @endif
                                                            </p>
                                                        </div>
                                                        <div class="text-right">
                                                            <div class="text-2xl font-bold text-indigo-600">
                                                                ${{ number_format($rate->total_rate, 2) }}
                                                            </div>
                                                            <div class="text-xs text-gray-500 mt-1">
                                                                Base: ${{ number_format($rate->base_rate, 2) }}<br>
                                                                Handling: ${{ number_format($rate->handling_fee, 2) }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- Overnight Services -->
                        @if(count($overnightRates) > 0)
                            <div class="mb-6 rate-section">
                                <h3 class="text-lg font-semibold text-gray-900 mb-3 border-b pb-2">Overnight Services</h3>
                                <div class="space-y-3">
                                    @foreach($overnightRates as $rate)
                                        <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-200">
                                            <label class="flex items-center p-4 cursor-pointer hover:bg-gray-50">
                                                <input
                                                    type="radio"
                                                    name="selected_rate"
                                                    value="{{ $rate->id }}"
                                                    class="mr-4 text-indigo-600 w-4 h-4"
                                                    required
                                                >
                                                <div class="flex-1">
                                                    <div class="flex justify-between items-start">
                                                        <div>
                                                            <h3 class="text-lg font-semibold text-gray-900">
                                                                {{ $rate->getServiceDisplayName() }}
                                                            </h3>
                                                            <p class="text-sm text-gray-600 mt-1">
                                                                @if($rate->transit_days)
                                                                    Estimated delivery: {{ $rate->transit_days }} business day(s)
                                                                @endif
                                                            </p>
                                                        </div>
                                                        <div class="text-right">
                                                            <div class="text-2xl font-bold text-indigo-600">
                                                                ${{ number_format($rate->total_rate, 2) }}
                                                            </div>
                                                            <div class="text-xs text-gray-500 mt-1">
                                                                Base: ${{ number_format($rate->base_rate, 2) }}<br>
                                                                Handling: ${{ number_format($rate->handling_fee, 2) }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- Other Services -->
                        @if(count($otherRates) > 0)
                            <div class="mb-6 rate-section">
                                <h3 class="text-lg font-semibold text-gray-900 mb-3 border-b pb-2">Other Services</h3>
                                <div class="space-y-3">
                                    @foreach($otherRates as $rate)
                                        <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-200">
                                            <label class="flex items-center p-4 cursor-pointer hover:bg-gray-50">
                                                <input
                                                    type="radio"
                                                    name="selected_rate"
                                                    value="{{ $rate->id }}"
                                                    class="mr-4 text-indigo-600 w-4 h-4"
                                                    required
                                                >
                                                <div class="flex-1">
                                                    <div class="flex justify-between items-start">
                                                        <div>
                                                            <h3 class="text-lg font-semibold text-gray-900">
                                                                {{ $rate->getServiceDisplayName() }}
                                                            </h3>
                                                            <p class="text-sm text-gray-600 mt-1">
                                                                @if($rate->transit_days)
                                                                    Estimated delivery: {{ $rate->transit_days }} business day(s)
                                                                @endif
                                                            </p>
                                                        </div>
                                                        <div class="text-right">
                                                            <div class="text-2xl font-bold text-indigo-600">
                                                                ${{ number_format($rate->total_rate, 2) }}
                                                            </div>
                                                            <div class="text-xs text-gray-500 mt-1">
                                                                Base: ${{ number_format($rate->base_rate, 2) }}<br>
                                                                Handling: ${{ number_format($rate->handling_fee, 2) }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- Show message if no rates match the delivery type preference -->
                        @if(count($filteredRates) === 0)
                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center">
                                <div class="mb-4">
                                    <svg class="mx-auto h-12 w-12 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-medium text-yellow-800 mb-2">No rates match your delivery preference</h3>
                                <p class="text-yellow-700 mb-4">
                                    We couldn't find any {{ $deliveryType }} delivery options for this route.
                                    Please try selecting a different delivery type or check back later.
                                </p>
                                <a href="{{ route('shipment.create', [
                                    'origin_state' => $shipment->origin_state,
                                    'destination_state' => $shipment->destination_state
                                ]) }}" class="bg-yellow-600 text-white px-6 py-3 rounded-md font-medium hover:bg-yellow-700">
                                    Try Different Options
                                </a>
                            </div>
                        @endif

                        <div class="mt-8 flex justify-between">
                            <a href="{{ route('home') }}" class="bg-gray-500 text-white px-6 py-3 rounded-md font-medium hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                                Back to Home
                            </a>
                            <button
                                type="submit"
                                class="bg-indigo-600 text-white px-8 py-3 rounded-md font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            >
                                Continue to Checkout
                            </button>
                        </div>
                    </form>
                @else
                    <div class="bg-white rounded-lg shadow-md p-8 text-center">
                        <div class="mb-6">
                            <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No Rates Available</h3>
                        <p class="text-gray-600 mb-6">
                            Unfortunately, we couldn't find any shipping rates for your destination.
                            Please check your shipment details and try again.
                        </p>
                        <a href="{{ route('home') }}" class="bg-indigo-600 text-white px-6 py-3 rounded-md font-medium hover:bg-indigo-700">
                            Start New Shipment
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-select first rate if only one option
        const rateInputs = document.querySelectorAll('input[name="selected_rate"]');
        if (rateInputs.length === 1) {
            rateInputs[0].checked = true;
        }

        // Toggle between filtered and all rates
        const toggleButton = document.getElementById('toggleAllRates');
        const allRateSections = document.querySelectorAll('.rate-section');
        let showingAllRates = false;

        if (toggleButton) {
            toggleButton.addEventListener('click', function() {
                showingAllRates = !showingAllRates;

                if (showingAllRates) {
                    // Show all rate sections
                    allRateSections.forEach(section => {
                        section.style.display = 'block';
                    });
                    toggleButton.textContent = 'Show Filtered Rates';

                    // Update header message
                    const header = document.querySelector('.bg-blue-50 h3');
                    if (header) {
                        header.innerHTML = 'Showing <span class="font-semibold">all available</span> rates';
                    }
                } else {
                    // Show only filtered rates (this would require server-side filtering)
                    // For now, just change the button text
                    toggleButton.textContent = 'Show All Rates';

                    // Update header message
                    const header = document.querySelector('.bg-blue-50 h3');
                    if (header) {
                        header.innerHTML = 'Available FedEx Services';
                    }
                }
            });
        }
    });
</script>
@endpush
@endsection
