@extends('layout')

@section('title', 'Payment Cancelled - BagVoyage')

@section('content')
<div class="py-12 bg-gray-50">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Cancel Header -->
        <div class="bg-white rounded-lg shadow-md p-8 text-center mb-8">
            <div class="mb-6">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-amber-100 rounded-full mb-4">
                    <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Payment Cancelled</h1>
                <p class="text-xl text-gray-600 mb-6">Your payment was cancelled and no charges were made</p>

                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('home') }}" class="bg-indigo-600 text-white px-6 py-3 rounded-md font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        Return to Home
                    </a>
                    <a href="{{ url()->previous() }}" class="bg-white text-indigo-600 border border-indigo-600 px-6 py-3 rounded-md font-medium hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        Try Again
                    </a>
                </div>
            </div>
        </div>

        <!-- Additional Information -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">What Happens Now?</h2>

            <div class="space-y-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0 w-6 h-6 bg-gray-100 rounded-full flex items-center justify-center mr-3 mt-0.5">
                        <span class="text-sm font-medium text-gray-600">1</span>
                    </div>
                    <div>
                        <h3 class="font-medium text-gray-900">No Charges</h3>
                        <p class="text-sm text-gray-600">Your payment was cancelled and no charges have been made to your account.</p>
                    </div>
                </div>

                <div class="flex items-start">
                    <div class="flex-shrink-0 w-6 h-6 bg-gray-100 rounded-full flex items-center justify-center mr-3 mt-0.5">
                        <span class="text-sm font-medium text-gray-600">2</span>
                    </div>
                    <div>
                        <h3 class="font-medium text-gray-900">Shipment Status</h3>
                        <p class="text-sm text-gray-600">Your shipment has not been processed and will remain in a pending state.</p>
                    </div>
                </div>

                <div class="flex items-start">
                    <div class="flex-shrink-0 w-6 h-6 bg-gray-100 rounded-full flex items-center justify-center mr-3 mt-0.5">
                        <span class="text-sm font-medium text-gray-600">3</span>
                    </div>
                    <div>
                        <h3 class="font-medium text-gray-900">Try Again</h3>
                        <p class="text-sm text-gray-600">You can try the payment again by clicking the "Try Again" button above, or start a new shipment.</p>
                    </div>
                </div>
            </div>

            <div class="mt-6 pt-6 border-t border-gray-200">
                <p class="text-sm text-gray-600">
                    If you encountered any issues during the payment process or have questions about your shipment,
                    please contact our support team at <a href="mailto:support@bagvoyage.com" class="text-indigo-600 hover:text-indigo-500">support@bagvoyage.com</a>.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
