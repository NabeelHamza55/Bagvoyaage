@extends('layout')

@section('title', 'BagVoyage - Domestic Shipping Made Easy')

@section('content')
<!-- Hero Section -->
<section class="relative bg-primary-600 overflow-hidden">
    <div class="absolute inset-0">
        <img src="{{ asset('images/hero-bg.jpg') }}" alt="Shipping Background" class="w-full h-full object-cover opacity-20">
    </div>
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-24 lg:py-32">
        <div class="text-center">
            <!-- Hero Title -->
            <h1 class="text-4xl lg:text-6xl font-bold text-white mb-6 animate-fade-in">
                Domestic Shipping<br>Made Simple
            </h1>

            <!-- Hero Description -->
            <p class="text-lg lg:text-xl text-white/90 mb-8 max-w-2xl mx-auto animate-fade-in" style="animation-delay: 0.2s;">
                Fast, reliable shipping across the United States. Get instant quotes, schedule pickups, and track your packages with ease.
            </p>

            <!-- Features -->
            <div class="flex flex-wrap justify-center gap-4 mb-12 animate-fade-in" style="animation-delay: 0.4s;">
                <div class="flex items-center gap-2 bg-white/20 backdrop-blur-sm rounded-full px-4 py-2">
                    <svg class="w-5 h-5 text-green-300" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <span class="text-sm font-medium">Fast delivery options</span>
                </div>
            </div>

            <!-- CTA Button -->
            <div class="animate-fade-in" style="animation-delay: 0.6s;">
                <a href="#start-form" class="inline-flex items-center btn btn-lg bg-white text-primary-600 hover:bg-gray-100 font-bold shadow-lg transform hover:scale-105 transition-all duration-200">
                    <span>Get Started Now</span>
                    <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                    </svg>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Origin/Destination Form Section -->
<section id="start-form" class="py-20 bg-white">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="text-3xl lg:text-4xl font-bold text-gray-900 mb-4">
                Get Started with Your Shipment
            </h2>
            <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                Select your origin and destination states to begin your domestic shipping journey.
                We only show states where FedEx service is available.
            </p>
        </div>

        <div class="card animate-slide-up">
            <div class="card-body p-8">
                <form method="GET" action="{{ route('shipment.form') }}" x-data="{ loading: false }" @submit="loading = true">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <!-- Origin State -->
                        <div class="space-y-2">
                            <label for="origin_state" class="block text-sm font-semibold text-gray-700">
                                <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                Origin State
                            </label>
                            <select
                                id="origin_state"
                                name="origin_state"
                                class="form-select @error('origin_state') border-red-500 @enderror"
                                required
                            >
                                <option value="">Select Origin State</option>
                                @foreach($states as $code => $name)
                                    <option value="{{ $code }}" {{ old('origin_state') == $code ? 'selected' : '' }}>
                                        {{ $name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('origin_state')
                                <p class="text-red-500 text-sm mt-1 flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                    </svg>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <!-- Destination State -->
                        <div class="space-y-2">
                            <label for="destination_state" class="block text-sm font-semibold text-gray-700">
                                <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                Destination State
                            </label>
                            <select
                                id="destination_state"
                                name="destination_state"
                                class="form-select @error('destination_state') border-red-500 @enderror"
                                required
                            >
                                <option value="">Select Destination State</option>
                                @foreach($states as $code => $name)
                                    <option value="{{ $code }}" {{ old('destination_state') == $code ? 'selected' : '' }}>
                                        {{ $name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('destination_state')
                                <p class="text-red-500 text-sm mt-1 flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                    </svg>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="mt-8 text-center">
                        <button
                            type="submit"
                            class="btn btn-lg bg-primary-600 text-white hover:bg-primary-700 w-full md:w-auto"
                            :class="{ 'opacity-75 cursor-not-allowed': loading }"
                            :disabled="loading"
                        >
                            <span x-show="!loading">Continue to Shipment Details</span>
                            <span x-show="loading" class="flex items-center justify-center">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Processing...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="py-20 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <h2 class="text-3xl lg:text-4xl font-bold text-gray-900 mb-4">
                Why Choose BagVoyage?
            </h2>
            <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                We make international shipping simple, secure, and affordable with our cutting-edge technology and trusted partnerships.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <!-- Feature 1 -->
            <div class="card text-center hover:shadow-xl transition-shadow duration-300">
                <div class="card-body p-8">
                    <div class="w-16 h-16 bg-gradient-success rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-4">Competitive Rates</h3>
                    <p class="text-gray-600">Get the best shipping rates with our FedEx integration plus transparent handling fees. No hidden costs, just honest pricing.</p>
                </div>
            </div>

            <!-- Feature 2 -->
            <div class="card text-center hover:shadow-xl transition-shadow duration-300">
                <div class="card-body p-8">
                    <div class="w-16 h-16 bg-gradient-primary rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-4">Secure Payments</h3>
                    <p class="text-gray-600">Pay securely with PayPal. Your financial information is protected with industry-standard encryption and fraud protection.</p>
                </div>
            </div>

            <!-- Feature 3 -->
            <div class="card text-center hover:shadow-xl transition-shadow duration-300">
                <div class="card-body p-8">
                    <div class="w-16 h-16 bg-gradient-secondary rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-4">Fast Delivery</h3>
                    <p class="text-gray-600">Multiple delivery options including express and overnight shipping to meet your needs. Track your package every step of the way.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Trust Section -->
<section class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-gray-900 mb-4">
                Trusted by Thousands of Customers
            </h2>
            <p class="text-lg text-gray-600">
                Join our growing community of satisfied customers who trust us with their international shipments.
            </p>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-8 items-center opacity-70">
            <div class="text-center">
                <div class="text-3xl font-bold text-primary-600 mb-2">98%</div>
                <div class="text-sm text-gray-600">Customer Satisfaction</div>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold text-primary-600 mb-2">50K+</div>
                <div class="text-sm text-gray-600">Packages Shipped</div>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold text-primary-600 mb-2">180+</div>
                <div class="text-sm text-gray-600">Countries Served</div>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold text-primary-600 mb-2">24/7</div>
                <div class="text-sm text-gray-600">Support Available</div>
            </div>
        </div>
    </div>
</section>
@endsection
