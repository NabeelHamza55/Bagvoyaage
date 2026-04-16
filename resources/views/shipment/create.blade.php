@extends('layout')

@section('title', 'Shipment Details - BagVoyage')

@section('content')
@php
    $f = $formDefaults ?? [];
@endphp
<div class="py-12 bg-gray-50">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Shipment Details</h1>
            <p class="text-gray-600">
                From: <span class="font-semibold">{{ $states[$origin_state] }}</span> →
                To: <span class="font-semibold">{{ $states[$destination_state] }}</span>
            </p>
        </div>

        <!-- Main Form -->
        <div class="bg-white rounded-lg shadow-md p-8">
            <!-- Display validation errors at the top -->
            @if ($errors->any())
                <div class="mb-6 bg-red-50 border border-red-200 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">
                                Please correct the following errors:
                            </h3>
                            <div class="mt-2 text-sm text-red-700">
                                <ul class="list-disc list-inside space-y-1">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('shipment.quote') }}">
                @csrf
                <input type="hidden" name="origin_state" value="{{ $origin_state }}">
                <input type="hidden" name="destination_state" value="{{ $destination_state }}">
                @if(!empty($resumeShipmentId))
                    <input type="hidden" name="resume_shipment_id" value="{{ $resumeShipmentId }}">
                @endif

                <!-- Sender Information -->
                <div class="mb-10">
                    <h2 class="text-xl font-semibold text-gray-900 mb-6 border-b pb-2">Sender Information</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label for="sender_full_name" class="block text-sm font-medium text-gray-700 mb-2">
                                Full Name *
                            </label>
                            <input
                                type="text"
                                id="sender_full_name"
                                name="sender_full_name"
                                value="{{ old('sender_full_name', $f['sender_full_name'] ?? '') }}"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('sender_full_name') border-red-500 @enderror"
                                required
                            >
                            @error('sender_full_name')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="sender_email" class="block text-sm font-medium text-gray-700 mb-2">
                                Email Address *
                            </label>
                            <input
                                type="email"
                                id="sender_email"
                                name="sender_email"
                                value="{{ old('sender_email', $f['sender_email'] ?? '') }}"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('sender_email') border-red-500 @enderror"
                                required
                            >
                            @error('sender_email')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="sender_phone" class="block text-sm font-medium text-gray-700 mb-2">
                                Phone Number *
                            </label>
                            <input
                                type="tel"
                                id="sender_phone"
                                name="sender_phone"
                                value="{{ old('sender_phone', $f['sender_phone'] ?? '') }}"
                                placeholder="+1-555-123-4567"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('sender_phone') border-red-500 @enderror"
                                required
                            >
                            @error('sender_phone')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Sender Address Fields -->
                        <div class="md:col-span-2">
                            <label for="sender_address_line" class="block text-sm font-medium text-gray-700 mb-2">
                                Street Address *
                            </label>
                            <input
                                type="text"
                                id="sender_address_line"
                                name="sender_address_line"
                                value="{{ old('sender_address_line', $f['sender_address_line'] ?? '') }}"
                                placeholder="123 Main St"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('sender_address_line') border-red-500 @enderror"
                                required
                            >
                            @error('sender_address_line')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="sender_city" class="block text-sm font-medium text-gray-700 mb-2">
                                City *
                            </label>
                            <input
                                type="text"
                                id="sender_city"
                                name="sender_city"
                                value="{{ old('sender_city', $f['sender_city'] ?? '') }}"
                                placeholder="Los Angeles"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('sender_city') border-red-500 @enderror"
                                required
                            >
                            @error('sender_city')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="sender_zipcode" class="block text-sm font-medium text-gray-700 mb-2">
                                ZIP Code *
                            </label>
                            <input
                                type="text"
                                id="sender_zipcode"
                                name="sender_zipcode"
                                value="{{ old('sender_zipcode', $f['sender_zipcode'] ?? '') }}"
                                placeholder="90001"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('sender_zipcode') border-red-500 @enderror"
                                required
                            >
                            @error('sender_zipcode')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Pickup vs drop-off is chosen on the rates page (after pricing), not here. --}}
                <input type="hidden" name="pickup_type" value="{{ old('pickup_type', $f['pickup_type'] ?? 'DROPOFF') }}">
                <input type="hidden" name="delivery_method" value="{{ old('delivery_method', $f['delivery_method'] ?? 'dropoff') }}">
                <input type="hidden" name="weight_unit" value="LB">
                <input type="hidden" name="dimension_unit" value="IN">
                <input type="hidden" name="currency_code" value="USD">
                <input type="hidden" name="packaging_type" value="YOUR_PACKAGING">
                <input type="hidden" name="sender_state" value="{{ $origin_state }}">

                <!-- Recipient Information -->
                <div class="mb-10">
                    <h2 class="text-xl font-semibold text-gray-900 mb-6 border-b pb-2">Recipient Information</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label for="recipient_name" class="block text-sm font-medium text-gray-700 mb-2">
                                Full Name *
                            </label>
                            <input
                                type="text"
                                id="recipient_name"
                                name="recipient_name"
                                value="{{ old('recipient_name', $f['recipient_name'] ?? '') }}"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('recipient_name') border-red-500 @enderror"
                                required
                            >
                            @error('recipient_name')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="recipient_phone" class="block text-sm font-medium text-gray-700 mb-2">
                                Phone Number *
                            </label>
                            <input
                                type="tel"
                                id="recipient_phone"
                                name="recipient_phone"
                                value="{{ old('recipient_phone', $f['recipient_phone'] ?? '') }}"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('recipient_phone') border-red-500 @enderror"
                                required
                            >
                            @error('recipient_phone')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label for="recipient_address" class="block text-sm font-medium text-gray-700 mb-2">
                                Street Address *
                            </label>
                            <textarea
                                id="recipient_address"
                                name="recipient_address"
                                rows="3"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('recipient_address') border-red-500 @enderror"
                                required
                            >{{ old('recipient_address', $f['recipient_address'] ?? '') }}</textarea>
                            @error('recipient_address')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="recipient_city" class="block text-sm font-medium text-gray-700 mb-2">
                                City *
                            </label>
                            <select
                                id="recipient_city"
                                name="recipient_city"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('recipient_city') border-red-500 @enderror"
                                required
                                onchange="toggleCustomCity('recipient_city', 'recipient_city_custom')"
                            >
                                <option value="">Select City</option>
                            </select>
                            <input
                                type="text"
                                id="recipient_city_custom"
                                name="recipient_city_custom"
                                placeholder="Enter custom city name"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 mt-2 hidden @error('recipient_city') border-red-500 @enderror"
                                value="{{ old('recipient_city_custom', $f['recipient_city_custom'] ?? '') }}"
                            >
                            @error('recipient_city')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="recipient_zip" class="block text-sm font-medium text-gray-700 mb-2">
                                ZIP Code *
                            </label>
                            <input
                                type="text"
                                id="recipient_zip"
                                name="recipient_zip"
                                value="{{ old('recipient_zip', $f['recipient_zip'] ?? '') }}"
                                placeholder="e.g., 90210"
                                data-state-code="{{ $destination_state }}"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('recipient_zip') border-red-500 @enderror"
                                required
                            >

                            @error('recipient_zip')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Package Information -->
                <div class="mb-10">
                    <h2 class="text-xl font-semibold text-gray-900 mb-6 border-b pb-2">Package Details</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="bag_type" class="block text-sm font-medium text-gray-700 mb-2">
                                Bag Type *
                            </label>
                            <select
                                id="bag_type"
                                name="bag_type"
                                class="w-full px-3 py-2 border {{ $errors->has('bag_type') ? 'border-red-500' : 'border-gray-300' }} rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                onchange="updateBagSpecifications()"
                                required
                            >
                                <option value="">Select Bag Type</option>
                                <option value="small" {{ old('bag_type', $f['bag_type'] ?? '') == 'small' ? 'selected' : '' }}>Small Bag (18" x 14" x 4", 25 lbs)</option>
                                <option value="medium" {{ old('bag_type', $f['bag_type'] ?? '') == 'medium' ? 'selected' : '' }}>Medium Bag (24" x 16" x 6", 40 lbs)</option>
                                <option value="large" {{ old('bag_type', $f['bag_type'] ?? '') == 'large' ? 'selected' : '' }}>Large Bag (28" x 20" x 8", 55 lbs)</option>
                            </select>
                            @error('bag_type')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="number_of_bags" class="block text-sm font-medium text-gray-700 mb-2">
                                Number of Bags *
                            </label>
                            <input
                                type="number"
                                id="number_of_bags"
                                name="number_of_bags"
                                value="{{ old('number_of_bags', $f['number_of_bags'] ?? 1) }}"
                                min="1"
                                max="10"
                                class="w-full px-3 py-2 border {{ $errors->has('number_of_bags') ? 'border-red-500' : 'border-gray-300' }} rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                onchange="updateBagSpecifications()"
                                required
                            >
                            @error('number_of_bags')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label for="declared_value" class="block text-sm font-medium text-gray-700 mb-2">
                                Declared Value ($)
                            </label>
                            <input
                                type="number"
                                step="0.01"
                                id="declared_value"
                                name="declared_value"
                                value="{{ old('declared_value', isset($f['declared_value']) ? number_format((float) $f['declared_value'], 2, '.', '') : '4.98') }}"
                                min="0.01"
                                class="w-full px-3 py-2 border {{ $errors->has('declared_value') ? 'border-red-500' : 'border-gray-300' }} rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                required
                            >
                            @error('declared_value')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Display calculated values -->
                        <div class="md:col-span-2" id="calculated-values" style="display: none;">
                            <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                                <h4 class="text-sm font-medium text-blue-800 mb-2">Calculated Package Details</h4>
                                <div class="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <span class="text-blue-600">Total Weight:</span>
                                        <span class="font-medium" id="display-weight">0 lbs</span>
                                    </div>
                                    <div>
                                        <span class="text-blue-600">Dimensions:</span>
                                        <span class="font-medium" id="display-dimensions">0" x 0" x 0"</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <label for="package_description" class="block text-sm font-medium text-gray-700 mb-2">
                                Package Description *
                            </label>
                            <textarea
                                id="package_description"
                                name="package_description"
                                rows="3"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('package_description') border-red-500 @enderror"
                                required
                            >{{ old('package_description', $f['package_description'] ?? '') }}</textarea>
                            @error('package_description')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Hidden fields for weight and dimensions - auto-populated by bag type -->
                        <input type="hidden" id="package_weight" name="package_weight" value="{{ old('package_weight', $f['package_weight'] ?? '') }}">
                        <input type="hidden" id="package_length" name="package_length" value="{{ old('package_length', $f['package_length'] ?? '') }}">
                        <input type="hidden" id="package_width" name="package_width" value="{{ old('package_width', $f['package_width'] ?? '') }}">
                        <input type="hidden" id="package_height" name="package_height" value="{{ old('package_height', $f['package_height'] ?? '') }}">

                        <!-- Show validation errors for hidden fields -->
                        @error('package_weight')
                            <div class="md:col-span-2">
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            </div>
                        @enderror
                        @error('package_length')
                            <div class="md:col-span-2">
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            </div>
                        @enderror
                        @error('package_width')
                            <div class="md:col-span-2">
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            </div>
                        @enderror
                        @error('package_height')
                            <div class="md:col-span-2">
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            </div>
                        @enderror
                    </div>
                </div>

                <!-- FedEx service (pricing is quoted for this service only) -->
                <div class="mb-10">
                    <h2 class="text-xl font-semibold text-gray-900 mb-6 border-b pb-2">FedEx service</h2>
                    <div class="max-w-xl">
                        <label for="service_type" class="block text-sm font-medium text-gray-700 mb-2">
                            Which service do you want to use? *
                        </label>
                        <select
                            id="service_type"
                            name="service_type"
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('service_type') border-red-500 @enderror"
                        >
                            <option value="">Select service</option>
                            <option value="FEDEX_GROUND" {{ old('service_type', $f['service_type'] ?? '') == 'FEDEX_GROUND' ? 'selected' : '' }}>FedEx Ground</option>
                            <option value="FEDEX_2_DAY" {{ old('service_type', $f['service_type'] ?? '') == 'FEDEX_2_DAY' ? 'selected' : '' }}>FedEx 2Day</option>
                            <option value="PRIORITY_OVERNIGHT" {{ old('service_type', $f['service_type'] ?? '') == 'PRIORITY_OVERNIGHT' ? 'selected' : '' }}>FedEx Priority Overnight</option>
                        </select>
                        @error('service_type')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                        <p class="mt-2 text-sm text-gray-500">
                            Ship date is not chosen on this step. We use the next valid business day for quoting; if you choose courier pickup on the rates page, your ship date follows the pickup date you enter before checkout.
                        </p>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-between">
                    <a href="{{ route('home') }}#start-form" class="bg-gray-500 text-white px-6 py-3 rounded-md font-medium hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                        Back
                    </a>
                    <button
                        type="submit"
                        class="bg-indigo-600 text-white px-8 py-3 rounded-md font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        Get Shipping Quote
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Load cities for a state (selectedValue = saved option value, city name, or custom text)
    function loadCitiesForState(stateCode, citySelect, selectedValue) {
        if (!stateCode) {
            citySelect.innerHTML = '<option value="">Select City</option>';
            return;
        }

        fetch(`{{ url('/cities') }}/${stateCode}`)
            .then(response => response.json())
            .then(data => {
                citySelect.innerHTML = '<option value="">Select City</option>';

                Object.entries(data.cities).forEach(([cityName, cityValue]) => {
                    const option = document.createElement('option');
                    option.value = cityValue;
                    option.textContent = cityName;
                    citySelect.appendChild(option);
                });

                // Add "Other" option
                const otherOption = document.createElement('option');
                otherOption.value = 'other';
                otherOption.textContent = 'Other (enter custom city)';
                citySelect.appendChild(otherOption);

                if (selectedValue) {
                    let matched = false;
                    for (let i = 0; i < citySelect.options.length; i++) {
                        const o = citySelect.options[i];
                        if (o.value === selectedValue || o.textContent === selectedValue) {
                            citySelect.value = o.value;
                            matched = true;
                            break;
                        }
                    }
                    if (!matched) {
                        citySelect.value = 'other';
                        const custom = document.getElementById('recipient_city_custom');
                        if (custom) {
                            custom.value = selectedValue;
                            custom.classList.remove('hidden');
                            custom.required = true;
                            citySelect.required = false;
                        }
                    }
                }
                toggleCustomCity('recipient_city', 'recipient_city_custom');
            })
            .catch(error => {
                console.error('Error loading cities:', error);
                citySelect.innerHTML = '<option value="">Error loading cities</option>';
            });
    }

    // Toggle custom city input
    function toggleCustomCity(citySelectId, customInputId) {
        const citySelect = document.getElementById(citySelectId);
        const customInput = document.getElementById(customInputId);

        if (citySelect.value === 'other') {
            customInput.classList.remove('hidden');
            customInput.required = true;
            citySelect.required = false;
        } else {
            customInput.classList.add('hidden');
            customInput.required = false;
            citySelect.required = true;
        }
    }

    // Update bag specifications when bag type or number changes
        function updateBagSpecifications() {
            const bagType = document.getElementById('bag_type').value;
            const numberOfBags = parseInt(document.getElementById('number_of_bags').value) || 1;
            const calculatedValuesDiv = document.getElementById('calculated-values');
            const numberOfBagsInput = document.getElementById('number_of_bags');

            if (bagType) {
                const bagSpecs = {
                    'small': { weight: 25, length: 18, width: 14, height: 4, name: 'Small Bag' },
                    'medium': { weight: 40, length: 24, width: 16, height: 6, name: 'Medium Bag' },
                    'large': { weight: 55, length: 28, width: 20, height: 8, name: 'Large Bag' }
                };

                const specs = bagSpecs[bagType];
                if (specs) {
                    // Update weight (total weight = single bag weight * number of bags)
                    const totalWeight = specs.weight * numberOfBags;
                    document.getElementById('package_weight').value = totalWeight;

                    // Update dimensions (single bag dimensions)
                    document.getElementById('package_length').value = specs.length;
                    document.getElementById('package_width').value = specs.width;
                    document.getElementById('package_height').value = specs.height;

                    // Show calculated values
                    const weightClass = 'text-blue-600';
                    const weightValueClass = 'font-medium';

                    document.getElementById('display-weight').textContent = totalWeight + ' lbs';
                    document.getElementById('display-weight').className = weightValueClass;
                    document.getElementById('display-dimensions').textContent = specs.length + '" x ' + specs.width + '" x ' + specs.height + '"';

                    // Update the calculated values display with warning
                    calculatedValuesDiv.innerHTML = `
                        <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                            <h4 class="text-sm font-medium text-blue-800 mb-2">Calculated Package Details</h4>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="${weightClass}">Total Weight:</span>
                                    <span class="${weightValueClass}">${totalWeight} lbs</span>
                                </div>
                                <div>
                                    <span class="text-blue-600">Dimensions:</span>
                                    <span class="font-medium">${specs.length}" x ${specs.width}" x ${specs.height}"</span>
                                </div>
                                <div>
                                    <span class="text-blue-600">Bag Type:</span>
                                    <span class="font-medium">${specs.name}</span>
                                </div>
                                <div>
                                    <span class="text-blue-600">Quantity:</span>
                                    <span class="font-medium">${numberOfBags} bag${numberOfBags > 1 ? 's' : ''}</span>
                                </div>
                            </div>
                        </div>
                    `;
                    calculatedValuesDiv.style.display = 'block';
                }
            } else {
                // Hide calculated values if no bag type selected
                calculatedValuesDiv.style.display = 'none';

                // Remove error styling
                numberOfBagsInput.classList.remove('border-red-500', 'border-green-500');
            }
        }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateBagSpecifications();

        // Add real-time validation for bag fields
        const bagTypeSelect = document.getElementById('bag_type');
        const numberOfBagsInput = document.getElementById('number_of_bags');

        if (bagTypeSelect) {
            bagTypeSelect.addEventListener('change', function() {
                if (this.value) {
                    this.classList.remove('border-red-500');
                    this.classList.add('border-green-500');
                } else {
                    this.classList.remove('border-green-500');
                    this.classList.add('border-red-500');
                }
            });
        }

        if (numberOfBagsInput) {
            numberOfBagsInput.addEventListener('input', function() {
                const value = parseInt(this.value);
                if (value >= 1 && value <= 10) {
                    this.classList.remove('border-red-500');
                    this.classList.add('border-green-500');
                } else {
                    this.classList.remove('border-green-500');
                    this.classList.add('border-red-500');
                }
            });
        }

        const recipientCity = document.getElementById('recipient_city');
        const destinationState = '{{ $destination_state }}';
        const recipientCityPreset = @json(old('recipient_city', $f['recipient_city'] ?? ''));

        if (destinationState && recipientCity) {
            loadCitiesForState(destinationState, recipientCity, recipientCityPreset);
        }
    });
</script>
@endpush
@endsection
