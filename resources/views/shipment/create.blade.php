@extends('layout')

@section('title', 'Shipment Details - BagVoyage')

@section('content')
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
            <form method="POST" action="{{ route('shipment.quote') }}">
                @csrf
                <input type="hidden" name="origin_state" value="{{ $origin_state }}">
                <input type="hidden" name="destination_state" value="{{ $destination_state }}">

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
                                value="{{ old('sender_full_name') }}"
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
                                value="{{ old('sender_email') }}"
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
                                value="{{ old('sender_phone') }}"
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
                                value="{{ old('sender_address_line') }}"
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
                                value="{{ old('sender_city') }}"
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
                                value="{{ old('sender_zipcode') }}"
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

                <!-- Pickup or Drop-off -->
                <div class="mb-10">
                    <h2 class="text-xl font-semibold text-gray-900 mb-6 border-b pb-2">Pickup or Drop-off</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="flex items-center p-4 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50">
                                <input
                                    type="radio"
                                    name="pickup_type"
                                    value="PICKUP"
                                    class="mr-3 text-indigo-600"
                                    {{ old('pickup_type') == 'PICKUP' ? 'checked' : '' }}
                                    onchange="togglePickupFields()"
                                >
                                <div>
                                    <div class="font-medium text-gray-900">Pickup</div>
                                    <div class="text-sm text-gray-500">We'll pick up from your location</div>
                                </div>
                            </label>
                        </div>
                        <div>
                            <label class="flex items-center p-4 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50">
                                <input
                                    type="radio"
                                    name="pickup_type"
                                    value="DROPOFF"
                                    class="mr-3 text-indigo-600"
                                    {{ old('pickup_type') == 'DROPOFF' ? 'checked' : '' }}
                                    onchange="togglePickupFields()"
                                >
                                <div>
                                    <div class="font-medium text-gray-900">Drop-off</div>
                                    <div class="text-sm text-gray-500">You'll drop off at FedEx location</div>
                                </div>
                            </label>
                        </div>
                    </div>
                    @error('pickup_type')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror

                    <!-- Hidden field for backward compatibility -->
                    <input type="hidden" name="delivery_method" id="delivery_method_hidden" value="{{ old('delivery_method', 'dropoff') }}">

                    <!-- Hidden fields for FedX API requirements -->
                    <input type="hidden" name="weight_unit" value="LB">
                    <input type="hidden" name="dimension_unit" value="IN">
                    <input type="hidden" name="currency_code" value="USD">
                    <input type="hidden" name="packaging_type" value="YOUR_PACKAGING">
                    <input type="hidden" name="sender_state" value="{{ $origin_state }}">

                    <!-- Pickup Address (conditional) -->
                    <div id="pickup-address-section" class="hidden">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Pickup Address</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <label for="pickup_address" class="block text-sm font-medium text-gray-700 mb-2">
                                    Street Address
                                </label>
                                <textarea
                                    id="pickup_address"
                                    name="pickup_address"
                                    rows="3"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                >{{ old('pickup_address') }}</textarea>
                            </div>
                            <div>
                                <label for="pickup_city" class="block text-sm font-medium text-gray-700 mb-2">
                                    City
                                </label>
                                <select
                                    id="pickup_city"
                                    name="pickup_city"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    required
                                    onchange="toggleCustomCity('pickup_city', 'pickup_city_custom')"
                                >
                                    <option value="">Select City</option>
                                </select>
                                <input
                                    type="text"
                                    id="pickup_city_custom"
                                    name="pickup_city_custom"
                                    placeholder="Enter custom city name"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 mt-2 hidden"
                                >
                            </div>
                            <div>
                                <label for="pickup_zip" class="block text-sm font-medium text-gray-700 mb-2">
                                    ZIP Code *
                                </label>
                                <input
                                    type="text"
                                    id="pickup_zip"
                                    name="pickup_zip"
                                    value="{{ old('pickup_zip') }}"
                                    placeholder="e.g., 10001"
                                    data-state-code="{{ $origin_state }}"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('pickup_zip') border-red-500 @enderror"
                                    required
                                >

                                @error('pickup_zip')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Pickup Scheduling (conditional - only if pickup is selected) -->
                    <div id="pickup-scheduling-section" class="hidden mt-8">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Pickup Scheduling</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="pickup_date" class="block text-sm font-medium text-gray-700 mb-2">
                                    Pickup Date *
                                </label>
                                <input
                                    type="date"
                                    id="pickup_date"
                                    name="pickup_date"
                                    value="{{ old('pickup_date') }}"
                                    min="{{ date('Y-m-d', strtotime('+1 day')) }}"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                >
                                @error('pickup_date')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="pickup_ready_time" class="block text-sm font-medium text-gray-700 mb-2">
                                        Ready Time *
                                    </label>
                                    <input
                                        type="time"
                                        id="pickup_ready_time"
                                        name="pickup_ready_time"
                                        value="{{ old('pickup_ready_time', '10:00') }}"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    >
                                    @error('pickup_ready_time')
                                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="pickup_close_time" class="block text-sm font-medium text-gray-700 mb-2">
                                        Close Time *
                                    </label>
                                    <input
                                        type="time"
                                        id="pickup_close_time"
                                        name="pickup_close_time"
                                        value="{{ old('pickup_close_time', '17:00') }}"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    >
                                    @error('pickup_close_time')
                                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="md:col-span-2">
                                <label for="pickup_instructions" class="block text-sm font-medium text-gray-700 mb-2">
                                    Special Instructions (Optional)
                                </label>
                                <textarea
                                    id="pickup_instructions"
                                    name="pickup_instructions"
                                    rows="3"
                                    placeholder="Ring bell at side entrance"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                >{{ old('pickup_instructions') }}</textarea>
                                @error('pickup_instructions')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

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
                                value="{{ old('recipient_name') }}"
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
                                value="{{ old('recipient_phone') }}"
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
                            >{{ old('recipient_address') }}</textarea>
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
                                value="{{ old('recipient_zip') }}"
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
                    <h2 class="text-xl font-semibold text-gray-900 mb-6 border-b pb-2">Package Information</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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
                            >{{ old('package_description') }}</textarea>
                            @error('package_description')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="package_weight" class="block text-sm font-medium text-gray-700 mb-2">
                                Weight (lbs) *
                            </label>
                            <input
                                type="number"
                                step="0.01"
                                id="package_weight"
                                name="package_weight"
                                value="{{ old('package_weight') }}"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('package_weight') border-red-500 @enderror"
                                required
                            >
                            @error('package_weight')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="declared_value" class="block text-sm font-medium text-gray-700 mb-2">
                                Declared Value ($) *
                            </label>
                            <input
                                type="number"
                                step="0.01"
                                id="declared_value"
                                name="declared_value"
                                value="{{ old('declared_value', '100.00') }}"
                                min="1.00"
                                placeholder="100.00"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('declared_value') border-red-500 @enderror"
                                required
                            >
                            @error('declared_value')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label for="package_length" class="block text-sm font-medium text-gray-700 mb-2">
                                    Length (in) *
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    id="package_length"
                                    name="package_length"
                                    value="{{ old('package_length') }}"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('package_length') border-red-500 @enderror"
                                    required
                                >
                                @error('package_length')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="package_width" class="block text-sm font-medium text-gray-700 mb-2">
                                    Width (in) *
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    id="package_width"
                                    name="package_width"
                                    value="{{ old('package_width') }}"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('package_width') border-red-500 @enderror"
                                    required
                                >
                                @error('package_width')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="package_height" class="block text-sm font-medium text-gray-700 mb-2">
                                    Height (in) *
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    id="package_height"
                                    name="package_height"
                                    value="{{ old('package_height') }}"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('package_height') border-red-500 @enderror"
                                    required
                                >
                                @error('package_height')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Shipping Preferences -->
                <div class="mb-10">
                    <h2 class="text-xl font-semibold text-gray-900 mb-6 border-b pb-2">Shipping Preferences</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="delivery_type" class="block text-sm font-medium text-gray-700 mb-2">
                                Preferred Delivery Type *
                            </label>
                            <select
                                id="delivery_type"
                                name="delivery_type"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('delivery_type') border-red-500 @enderror"
                                required
                            >
                                <option value="">Select Delivery Type</option>
                                <option value="standard" {{ old('delivery_type') == 'standard' ? 'selected' : '' }}>Standard</option>
                                <option value="express" {{ old('delivery_type') == 'express' ? 'selected' : '' }}>Express</option>
                                <option value="overnight" {{ old('delivery_type') == 'overnight' ? 'selected' : '' }}>Overnight</option>
                            </select>
                            @error('delivery_type')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="preferred_ship_date" class="block text-sm font-medium text-gray-700 mb-2">
                                Preferred Ship Date *
                            </label>
                            <input
                                type="date"
                                id="preferred_ship_date"
                                name="preferred_ship_date"
                                value="{{ old('preferred_ship_date') }}"
                                min="{{ date('Y-m-d') }}"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('preferred_ship_date') border-red-500 @enderror"
                                required
                            >
                            @error('preferred_ship_date')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-between">
                    <a href="{{ route('home') }}" class="bg-gray-500 text-white px-6 py-3 rounded-md font-medium hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
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
    function togglePickupFields() {
        const pickupRadio = document.querySelector('input[name="pickup_type"][value="PICKUP"]');
        const pickupAddressSection = document.getElementById('pickup-address-section');
        const pickupSchedulingSection = document.getElementById('pickup-scheduling-section');
        const deliveryMethodHidden = document.getElementById('delivery_method_hidden');

        if (pickupRadio && pickupRadio.checked) {
            pickupAddressSection.classList.remove('hidden');
            pickupSchedulingSection.classList.remove('hidden');
            deliveryMethodHidden.value = 'pickup';

            // Make pickup fields required
            document.getElementById('pickup_date').required = true;
            document.getElementById('pickup_ready_time').required = true;
            document.getElementById('pickup_close_time').required = true;
        } else {
            pickupAddressSection.classList.add('hidden');
            pickupSchedulingSection.classList.add('hidden');
            deliveryMethodHidden.value = 'dropoff';

            // Remove required attribute from pickup fields
            document.getElementById('pickup_date').required = false;
            document.getElementById('pickup_ready_time').required = false;
            document.getElementById('pickup_close_time').required = false;
        }
    }

    // Legacy function for backward compatibility
    function togglePickupAddress() {
        togglePickupFields();
    }

    // Load cities for a state
    function loadCitiesForState(stateCode, citySelect) {
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

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        togglePickupFields();

        // Get elements
        const pickupCity = document.getElementById('pickup_city');
        const recipientCity = document.getElementById('recipient_city');

        // Load initial cities based on selected states
        const originState = '{{ $origin_state }}';
        const destinationState = '{{ $destination_state }}';

        if (originState) {
            loadCitiesForState(originState, pickupCity);
        }

        if (destinationState) {
            loadCitiesForState(destinationState, recipientCity);
        }
    });
</script>
@endpush
@endsection
