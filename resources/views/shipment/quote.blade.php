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
            @if ($errors->any())
                <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                    <ul class="list-disc pl-5 space-y-0.5">
                        @foreach ($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        @php
            $uiDraft = $quoteUiDraft ?? [];
            $quotePickupType = old('pickup_type', $uiDraft['pickup_type'] ?? $shipment->pickup_type ?? 'DROPOFF');
            if (! in_array($quotePickupType, ['PICKUP', 'DROPOFF'], true)) {
                $quotePickupType = 'DROPOFF';
            }
            $validRateIds = collect($rates)->pluck('id')->map(static fn ($id) => (int) $id)->all();
            $candidateRateId = old('selected_rate', $uiDraft['selected_rate_id'] ?? $shipment->selected_rate_id);
            $quoteSelectedRateId = ($candidateRateId !== null && $candidateRateId !== '' && in_array((int) $candidateRateId, $validRateIds, true))
                ? (int) $candidateRateId
                : null;
        @endphp

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Shipment Summary + pickup choice (synced into main form via hidden field + JS) -->
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

                        @if($rates && count($rates) > 0)
                        <div>
                            <h3 class="font-medium text-gray-900 mb-2">Pickup or drop-off</h3>
                            <p class="text-xs text-gray-500 mb-3">Choose together with a FedEx service on the right, then <strong>Continue</strong>. If you pick <strong>Pickup</strong>, you’ll enter address and FedEx times on the next page.</p>
                            <div class="space-y-3">
                                <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-gray-200 p-3 text-sm hover:bg-gray-50">
                                    <input
                                        type="radio"
                                        name="quote_pickup_ui"
                                        value="DROPOFF"
                                        class="js-quote-pickup-ui mt-0.5 text-indigo-600"
                                        {{ $quotePickupType === 'DROPOFF' ? 'checked' : '' }}
                                    >
                                    <span>
                                        <span class="font-medium text-gray-900">Drop-off</span>
                                        <span class="block text-gray-600">You bring the package to FedEx.</span>
                                    </span>
                                </label>
                                <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-gray-200 p-3 text-sm hover:bg-gray-50">
                                    <input
                                        type="radio"
                                        name="quote_pickup_ui"
                                        value="PICKUP"
                                        class="js-quote-pickup-ui mt-0.5 text-indigo-600"
                                        {{ $quotePickupType === 'PICKUP' ? 'checked' : '' }}
                                    >
                                    <span>
                                        <span class="font-medium text-gray-900">Pickup</span>
                                        <span class="block text-gray-600">FedEx collects from you (address &amp; window next).</span>
                                    </span>
                                </label>
                                @error('pickup_type')
                                    <p class="text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            @if($quotePickupType === 'PICKUP' && $shipment->pickup_postal_code)
                                <div class="mt-3 text-sm text-gray-700">
                                    <p class="font-medium text-gray-900">Saved pickup location</p>
                                    <p>{{ $shipment->pickup_address }}</p>
                                    <p>{{ $shipment->pickup_city }}, {{ $states[$shipment->pickup_state] ?? $shipment->pickup_state }} {{ $shipment->pickup_postal_code }}</p>
                                </div>
                            @endif
                        </div>
                        @endif

                        <div>
                            <h3 class="font-medium text-gray-900 mb-2">Requested FedEx service</h3>
                            <p class="text-sm text-gray-600">{{ str_replace('_', ' ', $shipment->service_type ?? '—') }}</p>
                            @if($quotePickupType === 'PICKUP')
                                <p class="mt-2 text-xs text-gray-500">Ship date is set from your pickup date before checkout.</p>
                            @else
                                <p class="mt-2 text-xs text-gray-500">Ship date for this quote: {{ $shipment->preferred_ship_date->format('F j, Y') }}.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Shipping Rates — single form: service + pickup_type -->
            <div class="lg:col-span-2">
                @if($rates && count($rates) > 0)
                    <form id="shipment-quote-form" method="POST" action="{{ route('shipment.select-rate', $shipment) }}"
                        data-shipment-id="{{ $shipment->id }}"
                        data-draft-url="{{ route('shipment.draft.quote', $shipment) }}">
                        @csrf
                        <input type="hidden" name="pickup_type" id="shipment-quote-pickup-type" value="{{ $quotePickupType }}">
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
                                        Choose service &amp; delivery
                                    </h3>
                                    <p class="text-sm text-blue-600 mt-1">
                                        Pick <strong>one FedEx service</strong> and confirm <strong>pickup or drop-off</strong> in the summary (left), then continue.
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
                                        <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-200 border-2 border-transparent has-[:checked]:border-indigo-500 has-[:checked]:ring-2 has-[:checked]:ring-indigo-500 has-[:checked]:[&_.rate-price]:text-indigo-600">
                                            <label class="flex items-center p-4 cursor-pointer hover:bg-gray-50">
                                                <input
                                                    type="radio"
                                                    name="selected_rate"
                                                    value="{{ $rate->id }}"
                                                    class="mr-4 text-indigo-600 w-4 h-4"
                                                    {{ (int) $quoteSelectedRateId === (int) $rate->id ? 'checked' : '' }}
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
                                                            <div class="rate-price text-2xl font-bold text-gray-900">
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
                                        <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-200 border-2 border-transparent has-[:checked]:border-indigo-500 has-[:checked]:ring-2 has-[:checked]:ring-indigo-500 has-[:checked]:[&_.rate-price]:text-indigo-600">
                                            <label class="flex items-center p-4 cursor-pointer hover:bg-gray-50">
                                                <input
                                                    type="radio"
                                                    name="selected_rate"
                                                    value="{{ $rate->id }}"
                                                    class="mr-4 text-indigo-600 w-4 h-4"
                                                    {{ (int) $quoteSelectedRateId === (int) $rate->id ? 'checked' : '' }}
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
                                                            <div class="rate-price text-2xl font-bold text-gray-900">
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
                                        <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-200 border-2 border-transparent has-[:checked]:border-indigo-500 has-[:checked]:ring-2 has-[:checked]:ring-indigo-500 has-[:checked]:[&_.rate-price]:text-indigo-600">
                                            <label class="flex items-center p-4 cursor-pointer hover:bg-gray-50">
                                                <input
                                                    type="radio"
                                                    name="selected_rate"
                                                    value="{{ $rate->id }}"
                                                    class="mr-4 text-indigo-600 w-4 h-4"
                                                    {{ (int) $quoteSelectedRateId === (int) $rate->id ? 'checked' : '' }}
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
                                                            <div class="rate-price text-2xl font-bold text-gray-900">
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
                                        <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-200 border-2 border-transparent has-[:checked]:border-indigo-500 has-[:checked]:ring-2 has-[:checked]:ring-indigo-500 has-[:checked]:[&_.rate-price]:text-indigo-600">
                                            <label class="flex items-center p-4 cursor-pointer hover:bg-gray-50">
                                                <input
                                                    type="radio"
                                                    name="selected_rate"
                                                    value="{{ $rate->id }}"
                                                    class="mr-4 text-indigo-600 w-4 h-4"
                                                    {{ (int) $quoteSelectedRateId === (int) $rate->id ? 'checked' : '' }}
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
                                                            <div class="rate-price text-2xl font-bold text-gray-900">
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
                                    We couldn’t find any rates for this route with the current package and service filter.
                                    Try adjusting your shipment details or service type on the previous step.
                                </p>
                                <a href="{{ route('shipment.form', [
                                    'origin_state' => $shipment->origin_state,
                                    'destination_state' => $shipment->destination_state,
                                    'resume' => $shipment->id,
                                ]) }}" class="bg-yellow-600 text-white px-6 py-3 rounded-md font-medium hover:bg-yellow-700">
                                    Try Different Options
                                </a>
                            </div>
                        @endif

                        <div class="mt-8 flex justify-between">
                            <a href="{{ route('shipment.form', ['origin_state' => $shipment->origin_state, 'destination_state' => $shipment->destination_state, 'resume' => $shipment->id]) }}" class="bg-gray-500 text-white px-6 py-3 rounded-md font-medium hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                                Back to shipment details
                            </a>
                            <button
                                type="submit"
                                class="bg-indigo-600 text-white px-8 py-3 rounded-md font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            >
                                Continue
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
        const pickupHidden = document.getElementById('shipment-quote-pickup-type');
        function syncPickupTypeHidden() {
            const ui = document.querySelector('input[name="quote_pickup_ui"]:checked');
            if (pickupHidden && ui) {
                pickupHidden.value = ui.value;
            }
        }
        document.querySelectorAll('.js-quote-pickup-ui').forEach(function (el) {
            el.addEventListener('change', syncPickupTypeHidden);
        });
        syncPickupTypeHidden();

        const quoteForm = document.getElementById('shipment-quote-form');
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        function quoteStorageKey(id) {
            return 'bagvoyage_quote_ui_' + id;
        }

        function readQuoteSnapshot() {
            syncPickupTypeHidden();
            const rateEl = quoteForm ? quoteForm.querySelector('input[name="selected_rate"]:checked') : null;
            return {
                pickup_type: pickupHidden ? pickupHidden.value : '',
                selected_rate_id: rateEl ? parseInt(rateEl.value, 10) : null,
            };
        }

        function applyQuoteSnapshot(snap) {
            if (!snap) return;
            if (snap.pickup_type === 'PICKUP' || snap.pickup_type === 'DROPOFF') {
                const ui = document.querySelector('input[name="quote_pickup_ui"][value="' + snap.pickup_type + '"]');
                if (ui) {
                    ui.checked = true;
                    syncPickupTypeHidden();
                }
            }
            if (snap.selected_rate_id && quoteForm) {
                const r = quoteForm.querySelector('input[name="selected_rate"][value="' + snap.selected_rate_id + '"]');
                if (r) {
                    r.checked = true;
                }
            }
        }

        function persistQuoteClient(snap, shipmentId) {
            try {
                sessionStorage.setItem(quoteStorageKey(shipmentId), JSON.stringify(snap));
            } catch (e) { /* private mode */ }
        }

        let quoteDraftTimer = null;
        function scheduleQuoteDraftSave() {
            if (!quoteForm || !quoteForm.dataset.draftUrl) return;
            clearTimeout(quoteDraftTimer);
            quoteDraftTimer = setTimeout(function () {
                const snap = readQuoteSnapshot();
                persistQuoteClient(snap, quoteForm.dataset.shipmentId);
                if (!snap.pickup_type) return;
                fetch(quoteForm.dataset.draftUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        pickup_type: snap.pickup_type,
                        selected_rate_id: snap.selected_rate_id,
                    }),
                }).then(function (res) {
                    if (res.status === 422) {
                        try {
                            sessionStorage.removeItem(quoteStorageKey(quoteForm.dataset.shipmentId));
                        } catch (e) { /* ignore */ }
                    }
                }).catch(function () { /* offline */ });
            }, 350);
        }

        if (quoteForm) {
            window.addEventListener('pageshow', function (e) {
                if (!e.persisted) return;
                const id = quoteForm.dataset.shipmentId;
                try {
                    const raw = sessionStorage.getItem(quoteStorageKey(id));
                    if (raw) {
                        applyQuoteSnapshot(JSON.parse(raw));
                    }
                } catch (err) { /* ignore */ }
            });

            document.querySelectorAll('.js-quote-pickup-ui').forEach(function (el) {
                el.addEventListener('change', scheduleQuoteDraftSave);
            });
            quoteForm.querySelectorAll('input[name="selected_rate"]').forEach(function (el) {
                el.addEventListener('change', scheduleQuoteDraftSave);
            });
            // Drop client snapshot if it references a rate row that is not on this page (stale after re-quote).
            try {
                const raw = sessionStorage.getItem(quoteStorageKey(quoteForm.dataset.shipmentId));
                if (raw) {
                    const snap = JSON.parse(raw);
                    if (snap && snap.selected_rate_id) {
                        const match = quoteForm.querySelector('input[name="selected_rate"][value="' + snap.selected_rate_id + '"]');
                        if (!match) {
                            sessionStorage.removeItem(quoteStorageKey(quoteForm.dataset.shipmentId));
                        }
                    }
                }
            } catch (e) { /* ignore */ }
            scheduleQuoteDraftSave();
        }

        if (quoteForm) {
            quoteForm.addEventListener('submit', function () {
                syncPickupTypeHidden();
                try {
                    sessionStorage.removeItem(quoteStorageKey(quoteForm.dataset.shipmentId));
                } catch (err) { /* ignore */ }
            });
        }

        // Auto-select first rate if only one option and nothing is checked yet
        const rateInputs = document.querySelectorAll('input[name="selected_rate"]');
        if (rateInputs.length === 1 && !document.querySelector('input[name="selected_rate"]:checked')) {
            rateInputs[0].checked = true;
            scheduleQuoteDraftSave();
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
                        header.textContent = 'Showing all available rates';
                    }
                } else {
                    toggleButton.textContent = 'Show All Rates';

                    const header = document.querySelector('.bg-blue-50 h3');
                    if (header) {
                        header.textContent = 'Choose service & delivery';
                    }
                }
            });
        }
    });
</script>
@endpush
@endsection
