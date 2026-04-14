@extends('layout')

@section('title', 'Schedule Pickup - BagVoyage')

@section('content')
<div class="py-12 bg-gray-50">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-lg shadow-md p-6">
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
                        <p class="font-medium">{{ ucwords(str_replace('_', ' ', $shipment->status)) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Delivery Type</p>
                        <p class="font-medium">{{ $shipment->selectedRate ? $shipment->selectedRate->getServiceDisplayName() : 'Service Selected' }}</p>
                    </div>
                </div>
            </div>

            <div class="mb-6 p-4 bg-green-50 rounded-md">
                <h2 class="text-lg font-semibold mb-2">Pickup Address</h2>
                <div class="text-sm text-gray-700">
                    <p>{{ $shipment->pickup_address }}</p>
                    <p>{{ $shipment->pickup_city }}, {{ $shipment->pickup_state }} {{ $shipment->pickup_postal_code }}</p>
                </div>
            </div>

            @if(isset($availability) && $availability['available'])
                <div class="mb-6 p-4 bg-indigo-50 rounded-md">
                    <h2 class="text-lg font-semibold mb-2">Pickup Availability</h2>
                    <div class="text-sm text-indigo-700">
                        <p><strong>Pickup is available for this location!</strong></p>
                        @if(isset($cutoff_time))
                            <p>Cutoff Time: {{ $cutoff_time }}</p>
                        @endif
                        @if(isset($access_time))
                            <p>Access Time: {{ $access_time }}</p>
                        @endif
                        <p class="mt-2">FedEx will pick up your package between 9:00 AM and 5:00 PM on the selected date.</p>
                    </div>
                </div>
            @endif

            <form action="{{ route('shipment.pickup.schedule', $shipment) }}" method="POST" class="mt-6">
                @csrf
                <div class="mb-6">
                    <label for="pickup_date" class="block text-sm font-medium text-gray-700 mb-1">Pickup Date</label>
                    <input type="date" name="pickup_date" id="pickup_date"
                        value="{{ $shipment->preferred_ship_date->format('Y-m-d') }}"
                        min="{{ now()->format('Y-m-d') }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div class="mb-6">
                    <label for="pickup_time_slot" class="block text-sm font-medium text-gray-700 mb-1">Preferred Pickup Time</label>
                    <select
                        id="pickup_time_slot"
                        name="pickup_time_slot"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                        required
                    >
                        <option value="">Select Time Slot</option>
                        <option value="morning" {{ old('pickup_time_slot', $shipment->pickup_time_slot) == 'morning' ? 'selected' : '' }}>Morning (8 AM - 12 PM)</option>
                        <option value="afternoon" {{ old('pickup_time_slot', $shipment->pickup_time_slot) == 'afternoon' ? 'selected' : '' }}>Afternoon (12 PM - 4 PM)</option>
                        <option value="evening" {{ old('pickup_time_slot', $shipment->pickup_time_slot) == 'evening' ? 'selected' : '' }}>Evening (4 PM - 7 PM)</option>
                    </select>
                    <p class="text-sm text-gray-500 mt-1">FedEx will pick up your package during the selected time window.</p>
                </div>

                <div class="mb-4">
                    <div class="flex items-center">
                        <input type="checkbox" name="confirm_pickup" id="confirm_pickup" class="mr-2" required>
                        <label for="confirm_pickup" class="text-sm">I confirm that the package will be ready for pickup at the address above on the selected date</label>
                    </div>
                </div>

                <div class="flex justify-between mt-8">
                    <a href="{{ route('shipment.success', $shipment) }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                        Back to Shipment Details
                    </a>
                    <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Schedule Pickup
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const pickupDateInput = document.getElementById('pickup_date');
        if (pickupDateInput) {
            // Function to get minimum pickup date based on cutoff time
            function getMinPickupDate() {
                const now = new Date();
                const cutoffHour = 15; // 3 PM
                
                // If past 3 PM, pickup must be tomorrow or later
                if (now.getHours() >= cutoffHour) {
                    const tomorrow = new Date();
                    tomorrow.setDate(tomorrow.getDate() + 1);
                    const year = tomorrow.getFullYear();
                    const month = String(tomorrow.getMonth() + 1).padStart(2, '0');
                    const day = String(tomorrow.getDate()).padStart(2, '0');
                    return `${year}-${month}-${day}`;
                } else {
                    const year = now.getFullYear();
                    const month = String(now.getMonth() + 1).padStart(2, '0');
                    const day = String(now.getDate()).padStart(2, '0');
                    return `${year}-${month}-${day}`;
                }
            }
            
            // Set min date dynamically
            const minDate = getMinPickupDate();
            pickupDateInput.setAttribute('min', minDate);
            
            // Update current value if needed
            if (pickupDateInput.value && pickupDateInput.value < minDate) {
                pickupDateInput.value = minDate;
            }
            
            // Validate on change
            pickupDateInput.addEventListener('change', function() {
                const selectedValue = this.value;
                const minDateValue = getMinPickupDate();
                
                if (selectedValue < minDateValue) {
                    const now = new Date();
                    if (now.getHours() >= 15) {
                        alert('It is past 3 PM cutoff time. Pickup must be scheduled for tomorrow or later.');
                    } else {
                        alert('Pickup date cannot be in the past.');
                    }
                    this.value = minDateValue;
                }
            });
            
            // Validate before form submission
            const forms = document.querySelectorAll('form');
            forms.forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    const selectedValue = pickupDateInput.value;
                    const minDateValue = getMinPickupDate();
                    
                    if (selectedValue < minDateValue) {
                        e.preventDefault();
                        alert('Invalid pickup date. Please select a valid date.');
                        pickupDateInput.value = minDateValue;
                        pickupDateInput.focus();
                        return false;
                    }
                });
            });
        }
    });
</script>
@endpush
