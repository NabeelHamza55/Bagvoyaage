@extends('layout')

@section('title', 'Schedule Pickup - BagVoyage')

@section('content')
<div class="py-12 bg-gray-50">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-6 overflow-hidden rounded-xl border-2 border-indigo-600 bg-indigo-600 shadow-lg">
            <div class="bg-indigo-50 px-5 py-4 sm:px-6 sm:py-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700">FedEx Pickup Request API</p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900">Schedule your pickup</h1>
                <p class="mt-2 text-sm text-gray-700">
                    Availability for your address and service was checked with FedEx. Choose your date and time window below; the request we send uses FedEx cutoff and <strong>access time</strong> rules from that response.
                </p>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-6">Confirm details</h2>

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
                <h2 class="text-lg font-semibold mb-2">Pickup Address (sent to FedEx)</h2>
                <div class="text-sm text-gray-700">
                    <p>{{ $fedexPickupAddress['streetLines'][0] ?? '' }}</p>
                    <p>{{ $fedexPickupAddress['city'] ?? '' }}, {{ $fedexPickupAddress['stateOrProvinceCode'] ?? '' }} {{ $fedexPickupAddress['postalCode'] ?? '' }}</p>
                </div>
            </div>

            @php
                $slot = $availabilitySlot ?? [];
                $readyOpts = $slot['readyTimeOptions'] ?? [];
                $latestOpts = $slot['latestTimeOptions'] ?? [];
                $defaultReady = $slot['defaultReadyTime'] ?? '15:00:00';
                $defaultLatest = $slot['defaultLatestTimeOptions'] ?? '18:00:00';
                $pickedDate = $slot['pickupDate'] ?? ($availability['dispatch_date_used'] ?? $shipment->pickup_date?->format('Y-m-d'));
                $fmtClock = function (?string $t) {
                    if (!$t) return '—';
                    $t = strlen(trim($t)) === 5 ? trim($t).':00' : trim($t);
                    try {
                        return \Carbon\Carbon::createFromFormat('H:i:s', $t)->format('g:i A');
                    } catch (\Throwable) {
                        return $t;
                    }
                };
            @endphp

            @if(isset($availability) && $availability['available'])
                <div class="mb-6 rounded-lg border-2 border-green-500 bg-green-50 p-5 shadow-sm">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-green-500 text-white">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h2 class="text-lg font-bold text-green-900">FedEx: pickup available</h2>
                            <p class="mt-1 text-sm text-green-800">Use the time options FedEx returned for your address and service.</p>
                            <dl class="mt-3 grid gap-2 text-sm text-green-900 sm:grid-cols-2">
                                @if(!empty($cutoff_time))
                                    <div class="rounded bg-white/60 px-3 py-2">
                                        <dt class="text-xs font-medium uppercase text-green-700">Cutoff</dt>
                                        <dd class="font-semibold">{{ $fmtClock($cutoff_time) }}</dd>
                                    </div>
                                @endif
                                @if(!empty($access_time))
                                    <div class="rounded bg-white/60 px-3 py-2">
                                        <dt class="text-xs font-medium uppercase text-green-700">Access time</dt>
                                        <dd class="font-semibold">{{ $access_time }}</dd>
                                    </div>
                                @endif
                                @if($pickedDate)
                                    <div class="rounded bg-white/60 px-3 py-2 sm:col-span-2">
                                        <dt class="text-xs font-medium uppercase text-green-700">FedEx pickup date</dt>
                                        <dd class="font-semibold">{{ \Carbon\Carbon::parse($pickedDate)->format('l, M j, Y') }}</dd>
                                    </div>
                                @endif
                            </dl>
                        </div>
                    </div>
                </div>
            @endif

            <form action="{{ route('shipment.pickup.schedule', $shipment) }}" method="POST" class="mt-6">
                @csrf
                <div class="mb-6">
                    <label for="pickup_date" class="block text-sm font-medium text-gray-700 mb-1">Pickup date</label>
                    <input type="date" name="pickup_date" id="pickup_date"
                        value="{{ old('pickup_date', $pickedDate ?? $shipment->pickup_date?->format('Y-m-d') ?? $shipment->preferred_ship_date->format('Y-m-d')) }}"
                        min="{{ now()->format('Y-m-d') }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Changing the date may require a new availability check; FedEx will validate on submit.</p>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2 mb-6">
                    <div>
                        <label for="pickup_ready_time" class="block text-sm font-medium text-gray-700 mb-1">Package ready time *</label>
                        <select id="pickup_ready_time" name="pickup_ready_time" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            @foreach($readyOpts as $t)
                                <option value="{{ $t }}" @selected(old('pickup_ready_time', $shipment->pickup_ready_time ?? $defaultReady) == $t)>{{ $fmtClock($t) }}</option>
                            @endforeach
                            @if(empty($readyOpts))
                                <option value="{{ $defaultReady }}" selected>{{ $fmtClock($defaultReady) }}</option>
                            @endif
                        </select>
                    </div>
                    <div>
                        <label for="pickup_close_time" class="block text-sm font-medium text-gray-700 mb-1">Latest pickup time *</label>
                        <select id="pickup_close_time" name="pickup_close_time" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            @foreach($latestOpts as $t)
                                <option value="{{ $t }}" @selected(old('pickup_close_time', $shipment->pickup_close_time ?? $defaultLatest) == $t)>{{ $fmtClock($t) }}</option>
                            @endforeach
                            @if(empty($latestOpts))
                                <option value="{{ $defaultLatest }}" selected>{{ $fmtClock($defaultLatest) }}</option>
                            @endif
                        </select>
                    </div>
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
