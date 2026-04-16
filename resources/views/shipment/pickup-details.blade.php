@extends('layout')

@section('title', 'Pickup Details - BagVoyage')

@section('content')
@php
    $pd = $pickupUiDraft ?? [];
@endphp
<div class="py-12 bg-gray-50 min-h-screen">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Header --}}
        <div class="mb-6">
            <div class="flex items-center gap-3 mb-1">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-700">
                    {{ $shipment->selectedRate?->getServiceDisplayName() ?? 'Selected service' }}
                </span>
                @if($availability)
                    <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        FedEx availability confirmed
                    </span>
                @endif
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Schedule your pickup</h1>
            <p class="mt-1 text-sm text-gray-500">FedEx will collect the package from your chosen address.</p>
        </div>

        {{-- Validation errors --}}
        @if ($errors->any())
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-red-800" role="alert">
            <p class="font-semibold text-sm">Please fix the following:</p>
            <ul class="mt-1 list-disc pl-5 text-sm space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        @if(!$availability)
        {{-- ═══════════════════════════════════════════════════════════
             PHASE 1 — Address + date → Check Availability
             ═══════════════════════════════════════════════════════════ --}}
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <form id="pickup-phase1-form" action="{{ route('shipment.pickup-details.save', $shipment) }}" method="POST"
                data-draft-url="{{ route('shipment.draft.pickup', $shipment) }}"
                data-shipment-id="{{ $shipment->id }}">
                @csrf
                <input type="hidden" name="action" value="check">

                {{-- Pickup address --}}
                <h2 class="text-base font-semibold text-gray-900 mb-4">Pickup location</h2>
                <p class="text-sm text-gray-500 mb-4">FedEx will collect the package here. You can use a different address than the sender's.</p>

                <div class="space-y-4">
                    <div>
                        <label for="pickup_address" class="block text-sm font-medium text-gray-700 mb-1">Street address <span class="text-red-500">*</span></label>
                        <textarea id="pickup_address" name="pickup_address" rows="2" required
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">{{ old('pickup_address', $pd['pickup_address'] ?? $shipment->pickup_address ?? $shipment->sender_address_line) }}</textarea>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="sm:col-span-2">
                            <label for="pickup_city" class="block text-sm font-medium text-gray-700 mb-1">City <span class="text-red-500">*</span></label>
                            <select id="pickup_city" name="pickup_city" required
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                onchange="toggleCustomCity()">
                                <option value="">Loading cities…</option>
                            </select>
                            <input type="text" id="pickup_city_custom" name="pickup_city_custom" placeholder="Enter city name"
                                class="mt-2 hidden w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                value="{{ old('pickup_city_custom', $pd['pickup_city_custom'] ?? '') }}">
                        </div>
                        <div>
                            <label for="pickup_zip" class="block text-sm font-medium text-gray-700 mb-1">ZIP code <span class="text-red-500">*</span></label>
                            <input type="text" id="pickup_zip" name="pickup_zip" required maxlength="10"
                                value="{{ old('pickup_zip', $pd['pickup_zip'] ?? $shipment->pickup_postal_code ?? $shipment->sender_zipcode) }}"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        </div>
                    </div>
                </div>

                {{-- Preferred date --}}
                <h2 class="text-base font-semibold text-gray-900 mt-8 mb-2">Preferred pickup date</h2>
                <p class="text-sm text-gray-500 mb-4">FedEx will confirm the exact date and available time windows after you check availability.</p>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="pickup_date" class="block text-sm font-medium text-gray-700 mb-1">Date <span class="text-red-500">*</span></label>
                        <input type="date" id="pickup_date" name="pickup_date" required
                            value="{{ old('pickup_date', $pd['pickup_date'] ?? $shipment->pickup_date?->format('Y-m-d') ?? date('Y-m-d', strtotime('+1 weekday'))) }}"
                            min="{{ date('H') >= 15 ? date('Y-m-d', strtotime('next weekday')) : date('Y-m-d') }}"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="pickup_instructions" class="block text-sm font-medium text-gray-700 mb-1">Instructions <span class="text-gray-400">(optional)</span></label>
                        <input type="text" id="pickup_instructions" name="pickup_instructions" maxlength="60"
                            placeholder="e.g. Ring bell at loading dock"
                            value="{{ old('pickup_instructions', $pd['pickup_instructions'] ?? $shipment->pickup_instructions) }}"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>
                </div>

                <div class="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                    <a href="{{ route('shipment.rates', $shipment) }}"
                        class="inline-flex justify-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                        ← Back to rates
                    </a>
                    <button type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-7 py-2.5 text-sm font-semibold text-white shadow hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Check FedEx Availability
                    </button>
                </div>
            </form>
        </div>

        @else
        {{-- ═══════════════════════════════════════════════════════════
             PHASE 2 — FedEx confirmed — pick ready/close times
             ═══════════════════════════════════════════════════════════ --}}

        @php
            $carrier        = $availability['carrier']                ?? 'FDXG';
            $carrierLabel   = $carrier === 'FDXG' ? 'FedEx Ground' : 'FedEx Express';
            $pickedDate     = $availability['pickupDate']             ?? $shipment->pickup_date?->format('Y-m-d');
            $cutOff         = $availability['cutOffTime']             ?? null;
            $accessHrs      = $availability['accessTime']['hours']    ?? 0;
            $accessMins     = $availability['accessTime']['minutes']  ?? 0;
            $residential    = $availability['residentialAvailable']   ?? false;
            $readyOpts      = $availability['readyTimeOptions']       ?? [];
            $defaultReady   = $availability['defaultReadyTime']       ?? '15:00:00';
            $latestOpts     = $availability['latestTimeOptions']      ?? [];
            $defaultLatest  = $availability['defaultLatestTimeOptions'] ?? '18:00:00';
            $scheduleDay    = $availability['scheduleDay']            ?? 'FUTURE_DAY';

            $pd2 = $pickupUiDraft ?? [];
            $pickReadyVal = old('pickup_ready_time', $pd2['pickup_ready_time'] ?? $defaultReady);
            $pickCloseVal = old('pickup_close_time', $pd2['pickup_close_time'] ?? $defaultLatest);
            if (! empty($readyOpts) && ! in_array($pickReadyVal, $readyOpts, true)) {
                $pickReadyVal = $defaultReady;
            }
            if (! empty($latestOpts) && ! in_array($pickCloseVal, $latestOpts, true)) {
                $pickCloseVal = $defaultLatest;
            }

            $fmtTime = fn($t) => \Carbon\Carbon::createFromFormat('H:i:s', $t)->format('g:i A');
            $pickedDateFormatted = $pickedDate ? \Carbon\Carbon::parse($pickedDate)->format('l, F j, Y') : '—';
        @endphp

        {{-- FedEx confirmed info cards --}}
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 mb-6">
            <div class="rounded-xl bg-green-50 border border-green-200 p-4 col-span-2">
                <p class="text-xs font-medium text-green-600 uppercase tracking-wide">Confirmed Pickup Date</p>
                <p class="mt-1 text-lg font-bold text-green-900">{{ $pickedDateFormatted }}</p>
                <p class="text-xs text-green-700 mt-0.5">{{ $scheduleDay === 'SAME_DAY' ? 'Same day pickup' : 'Future day pickup' }}</p>
            </div>
            <div class="rounded-xl bg-white border border-gray-200 p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Carrier</p>
                <p class="mt-1 font-bold text-gray-900 text-sm">{{ $carrierLabel }}</p>
                @if($residential)
                    <p class="text-xs text-indigo-600 mt-0.5">Residential ✓</p>
                @endif
            </div>
            <div class="rounded-xl bg-white border border-gray-200 p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Order by</p>
                @if($cutOff)
                    <p class="mt-1 font-bold text-gray-900 text-sm">{{ $fmtTime($cutOff) }}</p>
                @else
                    <p class="mt-1 text-sm text-gray-400">—</p>
                @endif
                <p class="text-xs text-gray-400 mt-0.5">Cut-off time</p>
            </div>
        </div>

        @if($accessHrs > 0 || $accessMins > 0)
        <div class="mb-5 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3.5">
            <svg class="mt-0.5 w-4 h-4 flex-shrink-0 text-amber-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
            <p class="text-sm text-amber-800">
                <strong>Access time:</strong> the package must be ready
                <strong>{{ $accessHrs > 0 ? $accessHrs . 'h ' : '' }}{{ $accessMins > 0 ? $accessMins . 'm ' : '' }}</strong>
                before the driver arrives. Your ready time plus this window must not exceed the cut-off time.
            </p>
        </div>
        @endif

        {{-- Pickup address summary (read-only, link to re-check) --}}
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5 mb-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Pickup address</p>
                    <p class="text-sm font-medium text-gray-900">{{ $shipment->pickup_address }}</p>
                    <p class="text-sm text-gray-600">{{ $shipment->pickup_city }}, {{ $shipment->pickup_state }} {{ $shipment->pickup_postal_code }}</p>
                </div>
                <a href="{{ route('shipment.pickup-details', ['shipment' => $shipment, 'reset' => 1]) }}"
                    class="flex-shrink-0 text-xs text-indigo-600 hover:text-indigo-800 font-medium">Change address</a>
            </div>
        </div>

        {{-- Phase 2 form: time selectors --}}
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <form id="pickup-phase2-form" action="{{ route('shipment.pickup-details.save', $shipment) }}" method="POST"
                data-draft-url="{{ route('shipment.draft.pickup', $shipment) }}"
                data-shipment-id="{{ $shipment->id }}">
                @csrf
                <input type="hidden" name="action" value="confirm">

                <h2 class="text-base font-semibold text-gray-900 mb-1">Choose your time window</h2>
                <p class="text-sm text-gray-500 mb-5">Select when the package will be ready and the latest time the driver can arrive.</p>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    {{-- Package ready time --}}
                    <div>
                        <label for="pickup_ready_time" class="block text-sm font-medium text-gray-700 mb-1">
                            Package ready time <span class="text-red-500">*</span>
                        </label>
                        <select id="pickup_ready_time" name="pickup_ready_time" required
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                            @foreach($readyOpts as $t)
                                <option value="{{ $t }}" @selected($t === $pickReadyVal)>{{ $fmtTime($t) }}</option>
                            @endforeach
                            @if(empty($readyOpts))
                                <option value="{{ $pickReadyVal }}" selected>{{ $fmtTime($pickReadyVal) }}</option>
                            @endif
                        </select>
                        <p class="mt-1 text-xs text-gray-400">When will the package be ready for FedEx?</p>
                    </div>

                    {{-- Latest / close time --}}
                    <div>
                        <label for="pickup_close_time" class="block text-sm font-medium text-gray-700 mb-1">
                            Latest pickup time <span class="text-red-500">*</span>
                        </label>
                        <select id="pickup_close_time" name="pickup_close_time" required
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                            @foreach($latestOpts as $t)
                                <option value="{{ $t }}" @selected($t === $pickCloseVal)>{{ $fmtTime($t) }}</option>
                            @endforeach
                            @if(empty($latestOpts))
                                <option value="{{ $pickCloseVal }}" selected>{{ $fmtTime($pickCloseVal) }}</option>
                            @endif
                        </select>
                        <p class="mt-1 text-xs text-gray-400">The latest the driver can access the premises.</p>
                    </div>
                </div>

                {{-- Instructions --}}
                <div class="mt-5">
                    <label for="pickup_instructions_confirm" class="block text-sm font-medium text-gray-700 mb-1">
                        Courier instructions <span class="text-gray-400">(optional)</span>
                    </label>
                    <input type="text" id="pickup_instructions_confirm" name="pickup_instructions" maxlength="60"
                        placeholder="e.g. Ring bell at loading dock"
                        value="{{ old('pickup_instructions', $pd['pickup_instructions'] ?? $shipment->pickup_instructions) }}"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    <p class="mt-1 text-xs text-gray-400">Max 60 characters. Sent directly to the FedEx courier.</p>
                </div>

                <div class="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                    <a href="{{ route('shipment.pickup-details', ['shipment' => $shipment, 'reset' => 1]) }}"
                        class="inline-flex justify-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                        ← Change address / date
                    </a>
                    <button type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-green-600 px-7 py-2.5 text-sm font-semibold text-white shadow hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        Save & Continue to Checkout
                    </button>
                </div>
            </form>
        </div>
        @endif

    </div>
</div>

@push('scripts')
<script>
function toggleCustomCity() {
    const sel    = document.getElementById('pickup_city');
    const custom = document.getElementById('pickup_city_custom');
    if (!sel || !custom) return;
    const isOther = sel.value === 'other';
    custom.classList.toggle('hidden', !isOther);
    custom.required = isOther;
    if (!isOther) {
        sel.required = true;
    }
}

function loadPickupCities(stateCode, selectedCity) {
    const sel = document.getElementById('pickup_city');
    if (!sel || !stateCode) return;
    sel.innerHTML = '<option value="">Loading…</option>';

    fetch(`{{ url('/cities') }}/${encodeURIComponent(stateCode)}`)
        .then(r => r.json())
        .then(data => {
            sel.innerHTML = '<option value="">Select city</option>';
            const cities = data.cities || {};
            Object.entries(cities).forEach(([name, val]) => {
                const o = document.createElement('option');
                o.value = val;
                o.textContent = name;
                sel.appendChild(o);
            });
            const other = document.createElement('option');
            other.value = 'other';
            other.textContent = 'Other (enter manually)';
            sel.appendChild(other);

            if (selectedCity) {
                let matched = false;
                for (let i = 0; i < sel.options.length; i++) {
                    const o = sel.options[i];
                    if (String(o.value) === String(selectedCity) || o.textContent === selectedCity) {
                        sel.value = o.value;
                        matched = true;
                        break;
                    }
                }
                if (!matched) {
                    sel.value = 'other';
                    const custom = document.getElementById('pickup_city_custom');
                    if (custom) {
                        custom.value = selectedCity;
                        custom.classList.remove('hidden');
                        custom.required = true;
                        sel.required = false;
                    }
                }
            }
            toggleCustomCity();
        })
        .catch(() => { sel.innerHTML = '<option value="">Select city</option>'; });
}

function pickupDraftStorageKey(id) {
    return 'bagvoyage_pickup_ui_' + id;
}

function readPickupPhase1Payload(form) {
    if (!form) return {};
    const fd = new FormData(form);
    const o = {};
    ['pickup_address', 'pickup_city', 'pickup_city_custom', 'pickup_zip', 'pickup_date', 'pickup_instructions'].forEach(k => {
        const v = fd.get(k);
        if (v !== null && v !== '') o[k] = v;
    });
    return o;
}

function readPickupPhase2Payload(form) {
    if (!form) return {};
    const fd = new FormData(form);
    const o = {};
    ['pickup_ready_time', 'pickup_close_time', 'pickup_instructions'].forEach(k => {
        const v = fd.get(k);
        if (v !== null && v !== '') o[k] = v;
    });
    return o;
}

document.addEventListener('DOMContentLoaded', function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let pickupTimer = null;

    function schedulePickupDraftSave(form) {
        if (!form || !form.dataset.draftUrl) return;
        clearTimeout(pickupTimer);
        pickupTimer = setTimeout(function () {
            const phase1 = document.getElementById('pickup-phase1-form');
            const phase2 = document.getElementById('pickup-phase2-form');
            const payload = Object.assign(
                {},
                phase1 ? readPickupPhase1Payload(phase1) : {},
                phase2 ? readPickupPhase2Payload(phase2) : {}
            );
            const id = form.dataset.shipmentId;
            try {
                sessionStorage.setItem(pickupDraftStorageKey(id), JSON.stringify(payload));
            } catch (e) { /* ignore */ }
            fetch(form.dataset.draftUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            }).catch(function () { /* offline */ });
        }, 400);
    }

    @if(!$availability)
    const state    = @json($shipment->origin_state);
    const oldCity  = @json(old('pickup_city', $pd['pickup_city'] ?? $shipment->pickup_city ?? $shipment->sender_city));
    loadPickupCities(state, oldCity);
    const p1 = document.getElementById('pickup-phase1-form');
    if (p1) {
        p1.querySelectorAll('textarea,select,input').forEach(function (el) {
            el.addEventListener('change', function () { schedulePickupDraftSave(p1); });
            el.addEventListener('input', function () { schedulePickupDraftSave(p1); });
        });
        schedulePickupDraftSave(p1);
    }
    @else
    const p2 = document.getElementById('pickup-phase2-form');
    if (p2) {
        p2.querySelectorAll('select,input').forEach(function (el) {
            el.addEventListener('change', function () { schedulePickupDraftSave(p2); });
            el.addEventListener('input', function () { schedulePickupDraftSave(p2); });
        });
        schedulePickupDraftSave(p2);
    }
    @endif

    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) return;
        @if(!$availability)
        const p1b = document.getElementById('pickup-phase1-form');
        if (!p1b) return;
        try {
            const raw = sessionStorage.getItem(pickupDraftStorageKey(p1b.dataset.shipmentId));
            if (!raw) return;
            const d = JSON.parse(raw);
            const addr = p1b.querySelector('[name="pickup_address"]');
            const zip = p1b.querySelector('[name="pickup_zip"]');
            const dt = p1b.querySelector('[name="pickup_date"]');
            const ins = p1b.querySelector('[name="pickup_instructions"]');
            if (d.pickup_address && addr) addr.value = d.pickup_address;
            if (d.pickup_zip && zip) zip.value = d.pickup_zip;
            if (d.pickup_date && dt) dt.value = d.pickup_date;
            if (d.pickup_instructions && ins) ins.value = d.pickup_instructions;
            const sc = @json($shipment->origin_state);
            if (d.pickup_city === 'other' && d.pickup_city_custom) {
                loadPickupCities(sc, d.pickup_city_custom);
            } else if (d.pickup_city) {
                loadPickupCities(sc, d.pickup_city);
            }
        } catch (err) { /* ignore */ }
        @endif
    });
});
</script>
@endpush
@endsection
