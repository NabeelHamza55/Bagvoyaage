<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Shipment;
use App\Models\ShipmentRate;
use App\Services\NotificationService;
use App\Services\StateService;
use App\Services\FedExServiceFixed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ShipmentController extends Controller
{
    protected $stateService;
    protected $fedexService;

    public function __construct(StateService $stateService, FedExServiceFixed $fedexService)
    {
        $this->stateService = $stateService;
        $this->fedexService = $fedexService;
    }

    /**
     * Display the homepage with origin/destination form
     */
    public function index()
    {
        $states = $this->fedexService->getAvailableStates();
        $stateOptions = collect($states)->map(fn (string $name, string $code) => [
            'code' => $code,
            'name' => $name,
        ])->values()->all();

        return view('shipment.index', [
            'states' => $states,
            'stateOptions' => $stateOptions,
        ]);
    }

    /**
     * Show the shipment creation form (GET request)
     */
    public function showCreateForm(Request $request)
    {
        $origin = $request->query('origin_state');
        $dest = $request->query('destination_state');

        // Browser "back" often lands here without query params — restore last states from session.
        if (! $origin || ! $dest) {
            $ctx = session('shipment_create_context');
            if (is_array($ctx) && ! empty($ctx['origin_state']) && ! empty($ctx['destination_state'])) {
                return redirect()->route('shipment.form', [
                    'origin_state' => $ctx['origin_state'],
                    'destination_state' => $ctx['destination_state'],
                ]);
            }

            return redirect()->route('home')
                ->withErrors(['error' => 'Please select origin and destination states first']);
        }

        $request->validate([
            'origin_state' => 'required|string|size:2',
            'destination_state' => 'required|string|size:2',
        ]);

        session()->put('shipment_create_context', [
            'origin_state' => $origin,
            'destination_state' => $dest,
        ]);

        $states = $this->fedexService->getAvailableStates();

        $formDefaults = [];
        $resumeShipmentId = null;
        if ($request->filled('resume')) {
            $draft = Shipment::query()
                ->whereKey((int) $request->query('resume'))
                ->where('origin_state', $origin)
                ->where('destination_state', $dest)
                ->whereIn('status', ['pending', 'quote_received', 'payment_pending'])
                ->first();
            if ($draft) {
                $formDefaults = $this->createFormDefaultsFromShipment($draft);
                $resumeShipmentId = $draft->id;
            }
        }

        return view('shipment.create', [
            'states' => $states,
            'origin_state' => $origin,
            'destination_state' => $dest,
            'formDefaults' => $formDefaults,
            'resumeShipmentId' => $resumeShipmentId,
        ]);
    }

    /**
     * Handle origin/destination form submission
     */
    public function create(Request $request)
    {
        $request->validate([
            'origin_state' => 'required|string|size:2',
            'destination_state' => 'required|string|size:2',
        ]);

        $states = $this->fedexService->getAvailableStates();

        return view('shipment.create', [
            'states' => $states,
            'origin_state' => $request->origin_state,
            'destination_state' => $request->destination_state,
            'formDefaults' => [],
            'resumeShipmentId' => null,
        ]);
    }

    /**
     * Process detailed shipment form and get FedEx rates
     */
    public function getQuote(Request $request)
    {
        $validated = $request->validate([
            'origin_state' => 'required|string|size:2',
            'destination_state' => 'required|string|size:2',
            // Sender Information (Required for FedX API)
            'sender_full_name' => 'required|string|max:255',
            'sender_email' => 'required|email|max:255',
            'sender_phone' => 'required|string|max:20',
            'sender_address_line' => 'required|string|max:255',
            'sender_city' => 'required|string|max:100',
            'sender_state' => 'required|string|size:2',
            'sender_zipcode' => 'required|string|max:10',
            // Pickup/Delivery Method (pickup address & date collected after rates, before checkout)
            'pickup_type' => 'required|in:PICKUP,DROPOFF',
            'delivery_method' => 'required|in:pickup,dropoff',
            'packaging_type' => 'required|string|max:50',
            // FedEx service the customer wants pricing for (filters rate results)
            'service_type' => 'required|string|in:FEDEX_GROUND,FEDEX_2_DAY,PRIORITY_OVERNIGHT',
            // Recipient Information (Required)
            'recipient_name' => 'required|string|max:255',
            'recipient_phone' => 'required|string|max:20',
            'recipient_address' => 'required|string|max:500',
            'recipient_city' => 'required|string|max:100',
            'recipient_city_custom' => 'nullable|string|max:100',
            'recipient_zip' => 'required|string|max:10',
            'resume_shipment_id' => 'nullable|integer',
            // Package Information (Required)
            'package_length' => 'nullable|numeric|min:0.01|max:108',
            'package_width' => 'nullable|numeric|min:0.01|max:70',
            'package_height' => 'nullable|numeric|min:0.01|max:70',
            'package_weight' => 'nullable|numeric|min:0.01',
            'weight_unit' => 'required|in:LB,KG',
            'dimension_unit' => 'required|in:IN,CM',
            'package_description' => 'required|string|max:500',
            'bag_type' => 'required|in:small,medium,large',
            'number_of_bags' => 'required|integer|min:1|max:10',
            'declared_value' => 'required|numeric|min:1.00|max:50000.00',
            'currency_code' => 'required|string|size:3',
        ]);

        // Placeholder ship date for FedEx rate shopping only; for PICKUP it is replaced from pickup date before checkout.
        $validated['preferred_ship_date'] = $this->defaultPreferredShipDateForQuote();

        // Auto-set weight and dimensions based on bag type
        if (isset($validated['bag_type']) && isset($validated['number_of_bags'])) {
            $bagSpecs = [
                'small' => ['weight' => 25, 'length' => 18, 'width' => 14, 'height' => 4],
                'medium' => ['weight' => 40, 'length' => 24, 'width' => 16, 'height' => 6],
                'large' => ['weight' => 55, 'length' => 28, 'width' => 20, 'height' => 8]
            ];

            if (isset($bagSpecs[$validated['bag_type']])) {
                $specs = $bagSpecs[$validated['bag_type']];
                $calculatedWeight = $specs['weight'] * $validated['number_of_bags'];

                $validated['package_weight'] = $calculatedWeight;
                $validated['package_length'] = $specs['length'];
                $validated['package_width'] = $specs['width'];
                $validated['package_height'] = $specs['height'];
            }
        } else {
            // If no bag type selected, require manual input
            if (empty($validated['package_weight']) || empty($validated['package_length']) ||
                empty($validated['package_width']) || empty($validated['package_height'])) {
                throw new \Exception('Please select a bag type or provide manual package dimensions.');
            }
        }

        // Create shipment data (pickup location & schedule are filled after rates, before checkout)
        $shipmentData = [
            ...$validated,
            'pickup_state' => $validated['origin_state'],
            'pickup_city' => null,
            'pickup_postal_code' => null,
            'pickup_address' => null,
            'pickup_date' => null,
            'pickup_time_slot' => null,
            'pickup_instructions' => null,
            'recipient_state' => $validated['destination_state'],
            'recipient_city' => $validated['recipient_city'] === 'other' ? $validated['recipient_city_custom'] : $validated['recipient_city'],
            'recipient_postal_code' => $validated['recipient_zip'],
            'status' => 'pending',
            'origin_country' => 'US',
            'destination_country' => 'US',
        ];
        unset($shipmentData['resume_shipment_id']);

        $resumeId = $request->input('resume_shipment_id');
        $existing = null;
        if ($resumeId) {
            $existing = Shipment::query()
                ->whereKey((int) $resumeId)
                ->where('origin_state', $validated['origin_state'])
                ->where('destination_state', $validated['destination_state'])
                ->whereIn('status', ['pending', 'quote_received', 'payment_pending'])
                ->first();
        }

        if ($existing) {
            $existing->update($shipmentData);
            $existing->rates()->delete();
            $existing->update(['selected_rate_id' => null]);
            $shipment = $existing->fresh();
        } else {
            $shipment = Shipment::create($shipmentData);
        }

        try {
            // Get FedEx rates
            $rates = $this->fedexService->getRates($shipment);
            $shipment->update(['status' => 'quote_received']);

            // Rate row IDs may have changed — drop any stale quote-page UI draft for this shipment.
            session()->forget('shipment_quote_ui.' . $shipment->id);

            $this->rememberShipmentCreateStates($shipment);

            // POST/Redirect/GET: rates page must be a GET URL so browser "back" from checkout works.
            if (empty($rates)) {
                return redirect()->route('shipment.rates', $shipment)
                    ->with('error', 'No shipping rates for the FedEx service you selected on this route. Try another service or adjust package details.');
            }

            return redirect()->route('shipment.rates', $shipment)
                ->with('success', 'Your quote is ready. Choose pickup or drop-off and a FedEx service, then continue.');
        } catch (\Exception $e) {
            // Log the error
            Log::error('FedEx Rate Error in Controller: ' . $e->getMessage(), [
                'shipment_id' => $shipment->id ?? null,
                'package_dimensions' => [
                    'length' => $shipment->package_length ?? null,
                    'width' => $shipment->package_width ?? null,
                    'height' => $shipment->package_height ?? null,
                    'weight' => $shipment->package_weight ?? null,
                ],
            ]);

            $errorMessage = 'Unable to get shipping rates';

            if (strpos($e->getMessage(), 'PACKAGE.DIMENSIONS.EXCEEDED') !== false) {
                $errorMessage = 'Package dimensions or weight exceed FedEx limits. Please reduce the size or weight of your package.';
            } elseif (strpos($e->getMessage(), 'RATEREQUESTTYPE.REQUIRED') !== false) {
                $errorMessage = 'There was an issue with the shipping rate request. Please try again.';
            } elseif (strpos($e->getMessage(), 'FedX API') !== false) {
                $errorMessage = 'FedEx shipping service is temporarily unavailable. Please try again later.';
            }

            if (isset($shipment) && $shipment->exists) {
                $this->rememberShipmentCreateStates($shipment);

                return redirect()->route('shipment.rates', $shipment)->withErrors(['error' => $errorMessage]);
            }

            return back()->withErrors(['error' => $errorMessage])->withInput();
        }
    }

    /**
     * Show stored rates again (e.g. after checkout redirect when rate was missing).
     */
    public function showRates(Shipment $shipment)
    {
        if ($shipment->rates->isEmpty()) {
            return redirect()->route('home')->withErrors(['error' => 'No saved rates for this shipment. Start a new quote.']);
        }

        $this->rememberShipmentCreateStates($shipment);

        // User used "Back" from checkout (GET) — leave payment_pending so they can change rate/pickup cleanly.
        if ($shipment->status === 'payment_pending') {
            $shipment->update(['status' => 'quote_received']);
        }

        $this->synchronizeShipmentSelectedRate($shipment);

        $quoteUiDraft = $this->sanitizeQuoteUiDraftForShipment($shipment);

        return view('shipment.quote', [
            'shipment' => $shipment,
            'rates' => $shipment->rates,
            'states' => $this->fedexService->getAvailableStates(),
            'quoteUiDraft' => $quoteUiDraft,
        ]);
    }

    /**
     * Drop stale quote-page draft selections (e.g. old rate row IDs after re-quote).
     *
     * @return array<string, mixed>
     */
    private function sanitizeQuoteUiDraftForShipment(Shipment $shipment): array
    {
        $key = 'shipment_quote_ui.' . $shipment->id;
        $draft = session($key, []);
        $validIds = $shipment->rates()->pluck('id')->map(static fn ($id) => (int) $id)->all();

        if (! empty($draft['selected_rate_id']) && ! in_array((int) $draft['selected_rate_id'], $validIds, true)) {
            $draft['selected_rate_id'] = null;
        }
        if (isset($draft['pickup_type']) && ! in_array($draft['pickup_type'], ['PICKUP', 'DROPOFF'], true)) {
            unset($draft['pickup_type']);
        }

        session([$key => $draft]);

        return $draft;
    }

    /**
     * Keep shipments.selected_rate_id (source of truth) and shipment_rates.is_selected aligned.
     * Repairs orphaned FKs after rate rows are rebuilt and legacy rows that only had the boolean flag set.
     */
    private function synchronizeShipmentSelectedRate(Shipment $shipment): void
    {
        $shipment->loadMissing('rates');

        if ($shipment->rates->isEmpty()) {
            if ($shipment->selected_rate_id !== null) {
                $shipment->update(['selected_rate_id' => null]);
                $shipment->refresh();
            }

            return;
        }

        $valid = $shipment->rates->pluck('id')->map(static fn ($id) => (int) $id)->all();
        $fk = $shipment->selected_rate_id ? (int) $shipment->selected_rate_id : null;

        if ($fk && ! in_array($fk, $valid, true)) {
            $fk = null;
        }

        if ($fk === null) {
            $flagged = $shipment->rates->first(static fn (ShipmentRate $r) => (bool) $r->is_selected);
            if ($flagged) {
                $fk = (int) $flagged->id;
            }
        }

        DB::transaction(function () use ($shipment, $fk) {
            if ($fk === null) {
                $shipment->rates()->update(['is_selected' => false]);
                if ($shipment->selected_rate_id !== null) {
                    $shipment->update(['selected_rate_id' => null]);
                }

                return;
            }

            $shipment->rates()->where('id', '!=', $fk)->update(['is_selected' => false]);
            $shipment->rates()->where('id', $fk)->update(['is_selected' => true]);
            if ((int) $shipment->selected_rate_id !== $fk) {
                $shipment->update(['selected_rate_id' => $fk]);
            }
        });

        $shipment->refresh();
    }

    /**
     * Save quote-page selections (pickup vs drop-off + FedEx rate) for reload / navigation recovery.
     */
    public function saveQuoteDraft(Request $request, Shipment $shipment)
    {
        if ($shipment->rates->isEmpty()) {
            return response()->json(['ok' => false], 404);
        }

        $validated = $request->validate([
            'pickup_type' => 'required|in:PICKUP,DROPOFF',
            'selected_rate_id' => 'nullable|integer',
        ]);

        if (! empty($validated['selected_rate_id'])) {
            if (! $shipment->rates()->whereKey((int) $validated['selected_rate_id'])->exists()) {
                return response()->json(['ok' => false, 'error' => 'invalid_rate'], 422);
            }
        }

        session([
            'shipment_quote_ui.' . $shipment->id => [
                'pickup_type' => $validated['pickup_type'],
                'selected_rate_id' => $validated['selected_rate_id'] ?? null,
            ],
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Save pickup-details (phase 1) field values for reload / navigation recovery.
     */
    public function savePickupDraft(Request $request, Shipment $shipment)
    {
        if ($shipment->pickup_type !== 'PICKUP') {
            return response()->json(['ok' => false], 404);
        }

        $validated = $request->validate([
            'pickup_address' => 'nullable|string|max:500',
            'pickup_city' => 'nullable|string|max:100',
            'pickup_city_custom' => 'nullable|string|max:100',
            'pickup_zip' => 'nullable|string|max:10',
            'pickup_date' => 'nullable|date',
            'pickup_instructions' => 'nullable|string|max:500',
            'pickup_ready_time' => 'nullable|string|max:20',
            'pickup_close_time' => 'nullable|string|max:20',
        ]);

        $key = 'shipment_pickup_ui.' . $shipment->id;
        $merged = array_merge(session($key, []), array_filter(
            $validated,
            static fn ($v) => $v !== null && $v !== ''
        ));
        session([$key => $merged]);

        return response()->json(['ok' => true]);
    }

    /**
     * Persist selected rate after quote page, then pickup details (if pickup) or checkout.
     */
    public function selectRate(Request $request, Shipment $shipment)
    {
        $validated = $request->validate([
            'selected_rate' => [
                'required',
                'integer',
                Rule::exists('shipment_rates', 'id')->where('shipment_id', $shipment->id),
            ],
            'pickup_type' => 'required|in:PICKUP,DROPOFF',
        ]);

        // New rate / pickup path — drop cached FedEx availability from a previous attempt.
        session()->forget('pickup_availability');
        session()->forget('shipment_quote_ui.' . $shipment->id);

        $selectedRate = ShipmentRate::query()
            ->where('id', $validated['selected_rate'])
            ->where('shipment_id', $shipment->id)
            ->first();

        if (! $selectedRate) {
            return redirect()->route('shipment.rates', $shipment)->withErrors(['error' => 'Invalid rate selection.']);
        }

        $this->applyPickupPreference($shipment, $validated['pickup_type']);
        $shipment->refresh();

        DB::transaction(function () use ($shipment, $selectedRate) {
            $shipment->rates()->update(['is_selected' => false]);
            $selectedRate->update(['is_selected' => true]);
            $shipment->update(['selected_rate_id' => $selectedRate->id]);
        });
        $shipment->refresh();

        if ($shipment->pickup_type === 'PICKUP') {
            return redirect()->route('shipment.pickup-details', $shipment);
        }

        session()->forget('shipment_pickup_ui.' . $shipment->id);

        return redirect()->route('shipment.checkout', $shipment);
    }

    /**
     * Pickup address & window — required before checkout when pickup is selected.
     */
    public function showPickupDetails(Request $request, Shipment $shipment)
    {
        if ($shipment->pickup_type !== 'PICKUP') {
            return redirect()->route('shipment.checkout', $shipment);
        }

        $this->synchronizeShipmentSelectedRate($shipment);

        if (!$shipment->selectedRate) {
            return redirect()->route('shipment.rates', $shipment)
                ->withErrors(['error' => 'Please choose a shipping rate first.']);
        }

        if ($request->boolean('reset')) {
            session()->forget('pickup_availability');
            session()->forget('shipment_pickup_ui.' . $shipment->id);
        }

        // Stored in session (not flash) after Phase-1 check so refresh keeps Step 2.
        $availability = session('pickup_availability');

        return view('shipment.pickup-details', [
            'shipment'       => $shipment,
            'states'         => $this->fedexService->getAvailableStates(),
            'availability'   => $availability,
            'pickupUiDraft'  => session('shipment_pickup_ui.' . $shipment->id, []),
        ]);
    }

    /**
     * Two-phase pickup details handler.
     *
     * Phase 1 (action=check):  validate address+date, call FedEx availability, flash result, redirect back.
     * Phase 2 (action=confirm): validate user-chosen times from FedEx options, save, go to checkout.
     */
    public function savePickupDetails(Request $request, Shipment $shipment)
    {
        if ($shipment->pickup_type !== 'PICKUP') {
            return redirect()->route('shipment.checkout', $shipment);
        }

        $this->synchronizeShipmentSelectedRate($shipment);

        if (!$shipment->selectedRate) {
            return redirect()->route('shipment.rates', $shipment)
                ->withErrors(['error' => 'Please choose a shipping rate first.']);
        }

        $action = $request->input('action', 'check');

        /* ──────────────────────────────────────────────────────────────────
         * PHASE 1 — Check FedEx availability for the given address & date
         * ────────────────────────────────────────────────────────────────── */
        if ($action === 'check') {
            $cutoffHour    = 15;
            $now           = \Carbon\Carbon::now();
            $minPickupRule = ($now->hour >= $cutoffHour) ? 'after:today' : 'after_or_equal:today';

            $validated = $request->validate([
                'pickup_address'      => 'required|string|max:500',
                'pickup_city'         => 'required|string|max:100',
                'pickup_city_custom'  => 'nullable|string|max:100',
                'pickup_zip'          => 'required|string|max:10',
                'pickup_date'         => 'required|date|' . $minPickupRule,
                'pickup_instructions' => 'nullable|string|max:500',
            ]);

            $pickupCity = $validated['pickup_city'] === 'other'
                ? ($validated['pickup_city_custom'] ?? '')
                : $validated['pickup_city'];

            $pickupDateCarbon = \Carbon\Carbon::parse($validated['pickup_date'])->startOfDay();
            while ($pickupDateCarbon->isWeekend()) {
                $pickupDateCarbon->addDay();
            }

            // Persist address fields so Phase-2 form stays pre-filled.
            $shipment->update([
                'pickup_address'      => $validated['pickup_address'],
                'pickup_city'         => $pickupCity,
                'pickup_postal_code'  => preg_replace('/\D/', '', $validated['pickup_zip']),
                'pickup_state'        => $shipment->origin_state,
                'pickup_date'         => $pickupDateCarbon->format('Y-m-d'),
                'pickup_instructions' => $validated['pickup_instructions'] ?? null,
            ]);

            $shipment->refresh();
            $shipment->load('selectedRate');

            $result = $this->fedexService->checkPickupAvailability($shipment, $pickupDateCarbon);

            if (!$result['success'] || empty($result['available'])) {
                $msg = $result['message'] ?? 'FedEx pickup is not available for this address, date, or service.';
                return redirect()->back()->withInput()
                    ->withErrors(['pickup_availability' => $msg]);
            }

            $slot = $result['availability_slot'] ?? [];
            session()->put('pickup_availability', $slot);

            return redirect()->route('shipment.pickup-details', $shipment)->withInput();
        }

        /* ──────────────────────────────────────────────────────────────────
         * PHASE 2 — Save user-chosen times and proceed to checkout
         * ────────────────────────────────────────────────────────────────── */
        $validated = $request->validate([
            'pickup_ready_time'   => 'required|string',
            'pickup_close_time'   => 'required|string',
            'pickup_instructions' => 'nullable|string|max:500',
        ]);

        // The FedEx-confirmed pickup date was saved in Phase 1; derive ship date from it.
        $pickupDateCarbon = \Carbon\Carbon::parse($shipment->pickup_date)->startOfDay();
        $shipDateCarbon   = $pickupDateCarbon->copy()->addDay();
        while ($shipDateCarbon->isWeekend()) {
            $shipDateCarbon->addDay();
        }

        // Normalise to HH:MM:SS (the view sends "HH:MM:SS" already, but be safe).
        $readyTime = $this->normaliseTimeString($validated['pickup_ready_time']);
        $closeTime = $this->normaliseTimeString($validated['pickup_close_time']);

        $shipment->update([
            'pickup_ready_time'   => $readyTime,
            'pickup_close_time'   => $closeTime,
            'pickup_time_slot'    => null,
            'pickup_instructions' => $validated['pickup_instructions'] ?? null,
            'preferred_ship_date' => $shipDateCarbon->format('Y-m-d'),
        ]);

        session()->forget('pickup_availability');
        session()->forget('shipment_pickup_ui.' . $shipment->id);

        return redirect()->route('shipment.checkout', $shipment)
            ->with('success', 'Pickup times saved. Complete payment below.');
    }

    /** Ensure a time string is always HH:MM:SS. */
    private function normaliseTimeString(string $t): string
    {
        return preg_match('/^\d{2}:\d{2}$/', trim($t)) ? trim($t) . ':00' : trim($t);
    }

    /**
     * Display checkout page
     */
    public function checkout(Request $request, Shipment $shipment)
    {
        $this->synchronizeShipmentSelectedRate($shipment);

        $raw = $request->input('selected_rate');
        $selectedRateId = ($raw !== null && $raw !== '' && $raw !== false) ? (int) $raw : null;

        if ($selectedRateId) {
            $picked = $shipment->rates()->find($selectedRateId);

            if (! $picked) {
                return redirect()->route('shipment.rates', $shipment)->withErrors(['error' => 'Invalid rate selected']);
            }

            DB::transaction(function () use ($shipment, $picked) {
                $shipment->rates()->update(['is_selected' => false]);
                $picked->update(['is_selected' => true]);
                $shipment->update(['selected_rate_id' => $picked->id]);
            });
            $shipment->refresh();
            $this->synchronizeShipmentSelectedRate($shipment);
        }

        $selectedRate = $shipment->selectedRate;

        if (!$selectedRate) {
            return redirect()->route('shipment.rates', $shipment)->withErrors(['error' => 'Please select a shipping rate to continue.']);
        }

        if ($shipment->pickup_type === 'PICKUP' && $this->pickupDetailsIncomplete($shipment)) {
            return redirect()->route('shipment.pickup-details', $shipment)
                ->withErrors(['error' => 'Complete pickup address and schedule before checkout.']);
        }

        $shipment->update(['status' => 'payment_pending']);

        return view('shipment.checkout', [
            'shipment' => $shipment->fresh(['selectedRate']),
            'selectedRate' => $selectedRate,
            'states' => $this->stateService->getStates(),
        ]);
    }

    /**
     * Process PayPal payment
     */
    public function processPayment(Request $request, Shipment $shipment)
    {
        $this->synchronizeShipmentSelectedRate($shipment);

        $selectedRate = $shipment->selectedRate;

        if (!$selectedRate) {
            return redirect()->back()->withErrors(['error' => 'No rate selected']);
        }

        if ($shipment->pickup_type === 'PICKUP' && $this->pickupDetailsIncomplete($shipment)) {
            return redirect()->route('shipment.pickup-details', $shipment)
                ->withErrors(['error' => 'Complete pickup details and FedEx availability check before paying.']);
        }

        try {
            // Verify PayPal credentials are configured
            if (empty(config('services.paypal.client_id')) || empty(config('services.paypal.client_secret'))) {
                return redirect()->back()->withErrors([
                    'error' => 'PayPal API credentials are not configured. Please contact support.'
                ]);
            }

            // Validate and fix ship date if in the past
            $preferredDate = \Carbon\Carbon::parse($shipment->preferred_ship_date);
            if ($preferredDate->isPast()) {
                $today = \Carbon\Carbon::today();
                $shipment->preferred_ship_date = $today->format('Y-m-d');
                $shipment->save();

                Log::info('Ship date was in the past, adjusted to today', [
                    'shipment_id' => $shipment->id,
                    'original_date' => $preferredDate->format('Y-m-d'),
                    'adjusted_to' => $today->format('Y-m-d')
                ]);
            }

            // Create payment transaction
            $transaction = \App\Models\PaymentTransaction::create([
                'shipment_id' => $shipment->id,
                'amount' => $selectedRate->total_rate,
                'currency' => $selectedRate->currency,
                'status' => 'pending',
                'custom_id' => uniqid('trans_'),
            ]);

            // Process with PayPal
            $paypalService = new \App\Services\PayPalService();

            try {
                $paymentResult = $paypalService->createPayment($transaction);

                if ($paymentResult['success'] && !empty($paymentResult['approval_url'])) {
                    // Redirect to PayPal for payment approval
                    return redirect()->away($paymentResult['approval_url']);
                } else {
                    $transaction->update([
                        'status' => 'failed',
                        'error_response' => json_encode($paymentResult),
                    ]);

                    // Log detailed error
                    Log::error('PayPal payment initialization failed', [
                        'transaction_id' => $transaction->id,
                        'error' => $paymentResult['message'] ?? 'Unknown error',
                        'error_code' => $paymentResult['error_code'] ?? 'UNKNOWN',
                        'details' => $paymentResult
                    ]);

                    // Create user-friendly error message
                    $errorMessage = 'Payment initialization failed';

                    if (isset($paymentResult['error_code'])) {
                        switch ($paymentResult['error_code']) {
                            case 'INVALID_CLIENT':
                                $errorMessage = 'Payment service authentication failed. Please contact support.';
                                break;
                            case 'ORDER_CREATION_ERROR':
                                $errorMessage = 'Unable to create payment order. Please try again later.';
                                break;
                            case 'PROCESSING_ERROR':
                                $errorMessage = 'Payment processing error. Please try again or use a different payment method.';
                                break;
                            default:
                                $errorMessage = 'Payment initialization failed: ' . ($paymentResult['message'] ?? 'Unknown error');
                        }
                    }

                    return redirect()->back()->withErrors(['error' => $errorMessage]);
                }
            } catch (\Exception $e) {
                $transaction->update([
                    'status' => 'failed',
                    'error_response' => json_encode([
                        'error' => $e->getMessage()
                    ]),
                ]);

                Log::error('PayPal exception', [
                    'transaction_id' => $transaction->id,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                // Create user-friendly error message
                $errorMessage = 'Payment service error';

                if (strpos($e->getMessage(), 'credentials') !== false) {
                    $errorMessage = 'Payment service authentication failed. Please contact support.';
                } else {
                    $errorMessage = 'PayPal service error. Please try again later.';
                }

                return redirect()->back()->withErrors(['error' => $errorMessage]);
            }
        } catch (\Exception $e) {
            Log::error('General payment processing error', [
                'shipment_id' => $shipment->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->withErrors(['error' => 'Payment processing error. Please try again later.']);
        }
    }

    /**
     * Process shipment after payment is completed
     */
    protected function processShipmentAfterPayment(Shipment $shipment)
    {
        try {
            Log::info('Processing shipment after payment', [
                'shipment_id' => $shipment->id,
                'status' => 'paid'
            ]);

            // Update shipment status
            $shipment->status = 'paid';
            $shipment->save();

            // Create shipment with FedEx
            $response = $this->fedexService->createShipment($shipment);

            if ($response['success']) {
                // Update shipment with tracking information
                $shipment->tracking_number = $response['tracking_number'] ?? null;
                $shipment->status = 'shipment_created';
                $shipment->fedex_response = json_encode($response);

                // Update preferred_ship_date if it was adjusted to be valid
                if (!empty($response['actual_ship_date'])) {
                    $shipment->preferred_ship_date = $response['actual_ship_date'];
                }

                $shipment->save();

                // Create shipment tags after successful shipment creation
                Log::info('Creating shipment tags after successful shipment creation', [
                    'shipment_id' => $shipment->id,
                    'tracking_number' => $response['tracking_number'] ?? null,
                    'response_keys' => array_keys($response),
                    'has_label_url' => !empty($response['label_url']),
                    'has_base64_pdf' => !empty($response['base64_pdf']),
                    'has_response_data' => !empty($response['response_data'])
                ]);

                $tagResult = $this->fedexService->createShipmentTags($shipment, $response);
                Log::info("Tag creation result", [
                    'shipment_id' => $shipment->id,
                    'success' => $tagResult['success'] ?? false,
                    'message' => $tagResult['message'] ?? 'No message',
                    'has_tag_url' => !empty($tagResult['tag_url']),
                    'has_label_base64' => !empty($tagResult['label_base64']),
                    'tag_result_keys' => array_keys($tagResult)
                ]);
                if ($tagResult['success']) {
                    $labelUrl    = $tagResult['tag_url'] ?? null;
                    $labelBase64 = $tagResult['label_base64'] ?? null;

                    // Default response container
                    $currentFedexResponse = json_decode($shipment->fedex_response, true) ?: [];

                    if ($labelBase64) {
                        // FedEx gave base64 PDF/PNG label → save it locally
                        $filePath = storage_path("app/public/labels/{$shipment->id}.pdf");
                        file_put_contents($filePath, base64_decode($labelBase64));

                        // Always store a permanent local URL
                        $currentFedexResponse['label_url'] = asset("storage/labels/{$shipment->id}.pdf");
                    } elseif ($labelUrl) {
                        // FedEx gave a direct URL (temporary, but use if no base64)
                        $currentFedexResponse['label_url'] = $labelUrl;
                    } else {
                        // Neither base64 nor url → log an error
                        Log::error('FedEx did not return label data', [
                            'shipment_id' => $shipment->id,
                            'tagResult'   => $tagResult
                        ]);
                    }

                    $shipment->fedex_response = json_encode($currentFedexResponse);
                    $shipment->status = 'label_generated';
                    $shipment->save();

                    Log::info('Shipment tags created successfully', [
                        'shipment_id' => $shipment->id,
                        'label_url'   => $currentFedexResponse['label_url'] ?? null
                    ]);

                    // Ensure label is in public directory for web access
                    $publicLabelPath = public_path("storage/labels/{$shipment->id}.pdf");
                    if (!file_exists($publicLabelPath)) {
                        $publicLabelsDir = public_path("storage/labels");
                        if (!file_exists($publicLabelsDir)) {
                            mkdir($publicLabelsDir, 0755, true);
                        }

                        // Try to copy from storage if it exists there
                        $storageLabelPath = storage_path("app/public/labels/{$shipment->id}.pdf");
                        if (file_exists($storageLabelPath)) {
                            copy($storageLabelPath, $publicLabelPath);
                            Log::info('Label copied from storage to public directory', [
                                'shipment_id' => $shipment->id,
                                'public_path' => $publicLabelPath
                            ]);
                        }
                    }

                    // Send admin notification with label attachment after label is created
                    try {
                        $notificationService = new \App\Services\NotificationService();
                        $labelFilePath = $publicLabelPath; // Use public path for email attachment

                        $adminNotificationSent = $notificationService->sendAdminNewOrderNotification($shipment, $labelFilePath);
                        Log::info('Admin new order notification sent with label attachment', [
                            'shipment_id' => $shipment->id,
                            'success' => $adminNotificationSent,
                            'has_label_file' => file_exists($labelFilePath),
                            'label_file_path' => $labelFilePath
                        ]);
                    } catch (\Exception $adminEmailException) {
                        Log::error('Admin notification email failed', [
                            'shipment_id' => $shipment->id,
                            'error' => $adminEmailException->getMessage()
                        ]);
                    }

                } else {
                    Log::error('Shipment tag creation failed', [
                        'shipment_id' => $shipment->id,
                        'error'       => $tagResult['message'] ?? 'Unknown error'
                    ]);

                    // Send admin notification without label attachment if label creation failed
                    try {
                        $notificationService = new \App\Services\NotificationService();
                        $adminNotificationSent = $notificationService->sendAdminNewOrderNotification($shipment, null);
                        Log::info('Admin new order notification sent without label attachment', [
                            'shipment_id' => $shipment->id,
                            'success' => $adminNotificationSent,
                            'reason' => 'Label creation failed'
                        ]);
                    } catch (\Exception $adminEmailException) {
                        Log::error('Admin notification email failed', [
                            'shipment_id' => $shipment->id,
                            'error' => $adminEmailException->getMessage()
                        ]);
                    }
                }

                // Debug pickup scheduling conditions
                Log::info('Checking pickup scheduling conditions', [
                    'shipment_id'      => $shipment->id,
                    'pickup_type'      => $shipment->pickup_type,
                    'pickup_scheduled' => $shipment->pickup_scheduled,
                    'condition_met'    => ($shipment->pickup_type == 'PICKUP' && !$shipment->pickup_scheduled)
                ]);


                // Reset pickup_scheduled if it was set by old code (AUTO-* or FAILED-* confirmation)
                if ($shipment->pickup_scheduled && (
                    str_starts_with($shipment->pickup_confirmation ?? '', 'AUTO-') ||
                    str_starts_with($shipment->pickup_confirmation ?? '', 'FAILED-') ||
                    empty($shipment->pickup_confirmation)
                )) {
                    Log::info('Resetting pickup_scheduled from old invalid confirmation', [
                        'shipment_id' => $shipment->id,
                        'old_confirmation' => $shipment->pickup_confirmation,
                        'reason' => 'Invalid or missing confirmation number'
                    ]);
                    $shipment->pickup_scheduled = false;
                    $shipment->pickup_confirmation = null;
                    $shipment->save();
                }

                // If pickup was selected, schedule it with FedEx API
                if ($shipment->pickup_type == 'PICKUP' && !$shipment->pickup_scheduled) {
                    Log::info('✅ PICKUP SCHEDULING CONDITION MET - Calling FedEx API', [
                        'shipment_id' => $shipment->id,
                        'pickup_type' => $shipment->pickup_type,
                        'pickup_scheduled' => $shipment->pickup_scheduled
                    ]);

                    // Call the actual FedEx pickup API
                    $pickupResult = $this->fedexService->schedulePickup($shipment);

                    if ($pickupResult['success']) {
                        // Update shipment with actual FedEx pickup details
                        $shipment->pickup_scheduled = true;
                        $shipment->pickup_confirmation = $pickupResult['confirmation_number'] ?? null;
                        $shipment->pickup_date = $pickupResult['actual_pickup_date'] ?? $pickupResult['scheduled_date'] ?? null;

                        // Update preferred_ship_date if it was adjusted to ensure pickup < ship date
                        if (!empty($pickupResult['actual_ship_date'])) {
                            $shipment->preferred_ship_date = $pickupResult['actual_ship_date'];
                        }

                        $shipment->status = 'pickup_scheduled';
                        $shipment->save();

                        Log::info('FedEx pickup scheduled successfully after shipment creation', [
                            'shipment_id' => $shipment->id,
                            'confirmation_number' => $pickupResult['confirmation_number'],
                            'scheduled_date' => $pickupResult['scheduled_date'],
                            'carrier_code' => $pickupResult['carrier_code'] ?? null
                        ]);

                        // Send pickup confirmation email
                        $notificationService = new \App\Services\NotificationService();
                        $notificationService->sendPickupScheduled($shipment);
                    } else {
                        $errorCode = $pickupResult['error_code'] ?? null;
                        $recoverable = $errorCode === 'PICKUP_NOT_AVAILABLE'
                            || (isset($pickupResult['error']) && $pickupResult['error'] === 'pickup_service_unavailable');

                        if (isset($pickupResult['error']) && $pickupResult['error'] === 'pickup_service_unavailable') {
                            Log::warning('FedEx pickup service unavailable - shipment created but pickup failed', [
                                'shipment_id' => $shipment->id,
                                'error' => $pickupResult['error'],
                                'message' => $pickupResult['message'] ?? 'Service unavailable',
                                'can_retry' => $pickupResult['can_retry'] ?? false
                            ]);
                        } elseif ($errorCode === 'PICKUP_NOT_AVAILABLE') {
                            Log::warning('FedEx pickup not available after shipment creation — customer can reschedule from shipment page', [
                                'shipment_id' => $shipment->id,
                                'message' => $pickupResult['message'] ?? 'Pickup not available',
                            ]);
                        } else {
                            Log::error('FedEx pickup scheduling failed after shipment creation', [
                                'shipment_id' => $shipment->id,
                                'error' => $pickupResult['message'] ?? 'Unknown error',
                                'error_code' => $errorCode
                            ]);
                        }

                        if ($recoverable) {
                            $shipment->status = 'label_generated';
                            $shipment->pickup_scheduled = false;
                            $shipment->pickup_confirmation = null;
                        } else {
                            $shipment->pickup_scheduled = true;
                            $shipment->pickup_confirmation = 'FAILED-' . time();
                            Log::error('Pickup scheduling failed - error details', [
                                'shipment_id' => $shipment->id,
                                'error' => $pickupResult['message'] ?? 'Unknown error'
                            ]);
                        }
                        $shipment->save();
                    }
                }

                return true;
            } else {
                // Log error and update shipment status
                Log::error('FedX shipment creation failed after payment', [
                    'shipment_id' => $shipment->id,
                    'error' => $response['message'] ?? 'Unknown error'
                ]);

                $shipment->status = 'payment_completed_shipment_pending';
                $shipment->fedex_response = json_encode($response);
                $shipment->save();

                return false;
            }
        } catch (\Exception $e) {
            Log::error('Error processing shipment after payment', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage()
            ]);

            $shipment->status = 'payment_completed_shipment_pending';
            $shipment->save();

            return false;
        }
    }

    /**
     * Display shipment success page with all shipment management options
     */
    public function success(Shipment $shipment)
    {
        // Log the success page view
        Log::info('Shipment success page viewed', [
            'shipment_id' => $shipment->id,
            'status' => $shipment->status
        ]);

        return view('shipment.success', [
            'shipment' => $shipment,
            'states' => $this->stateService->getStates(),
        ]);
    }

    /**
     * Show the form for scheduling a pickup
     */
    public function showPickupForm(Shipment $shipment)
    {
        // Ensure shipment has been created
        if (!in_array($shipment->status, ['shipment_created', 'pickup_scheduled', 'label_generated', 'confirmed'])) {
            return redirect()->route('shipment.success', $shipment)
                ->withErrors(['error' => 'Shipment must be created before scheduling pickup']);
        }

        // Check pickup availability first according to FedEx documentation
        $availabilityResult = $this->fedexService->checkPickupAvailability($shipment);

        if (!$availabilityResult['success'] || !$availabilityResult['available']) {
            return redirect()->route('shipment.success', $shipment)
                ->withErrors(['error' => 'Pickup is not available for this location or service type. ' . ($availabilityResult['message'] ?? '')]);
        }

        $slot        = $availabilityResult['availability_slot'] ?? [];
        $accessRaw  = $availabilityResult['access_time'] ?? null;
        $accessDisplay = is_array($accessRaw)
            ? trim(
                ((int) ($accessRaw['hours'] ?? 0) > 0 ? (int) $accessRaw['hours'] . ' hr ' : '')
                . ((int) ($accessRaw['minutes'] ?? 0) > 0 ? (int) $accessRaw['minutes'] . ' min' : '')
            )
            : (string) ($accessRaw ?? '');

        return view('shipment.schedule-pickup', [
            'shipment'         => $shipment,
            'availability'     => $availabilityResult,
            'availabilitySlot' => $slot,
            'cutoff_time'      => $availabilityResult['cutoff_time'],
            'access_time'      => $accessDisplay !== '' ? $accessDisplay : null,
            'fedexPickupAddress' => $this->fedexService->resolvedPickupAddressForShipment($shipment),
        ]);
    }

    /**
     * Schedule a pickup with FedEx
     */
    public function schedulePickup(Request $request, Shipment $shipment)
    {
        $request->validate([
            'confirm_pickup'    => 'required|accepted',
            'pickup_date'       => 'nullable|date|after_or_equal:today',
            'pickup_ready_time' => 'required|string|regex:/^\d{2}:\d{2}(:\d{2})?$/',
            'pickup_close_time' => 'required|string|regex:/^\d{2}:\d{2}(:\d{2})?$/',
        ]);

        try {
            $ready = $this->normaliseTimeString($request->pickup_ready_time);
            $close = $this->normaliseTimeString($request->pickup_close_time);

            $updates = [
                'pickup_ready_time' => $ready,
                'pickup_close_time' => $close,
                'pickup_time_slot'  => null,
            ];

            if ($request->filled('pickup_date')) {
                $pickupDate = \Carbon\Carbon::parse($request->pickup_date)->startOfDay();
                while ($pickupDate->isWeekend()) {
                    $pickupDate->addDay();
                }
                $shipDate = $pickupDate->copy()->addDay();
                while ($shipDate->isWeekend()) {
                    $shipDate->addDay();
                }
                $updates['pickup_date']         = $pickupDate->format('Y-m-d');
                $updates['preferred_ship_date'] = $shipDate->format('Y-m-d');
            }

            $shipment->update($updates);
            $shipment->refresh();

            Log::info('Scheduling FedEx pickup from controller', [
                'shipment_id' => $shipment->id,
                'pickup_date' => $shipment->preferred_ship_date->format('Y-m-d')
            ]);

            $pickupResult = $this->fedexService->schedulePickup($shipment);

            if ($pickupResult['success']) {
                // Update shipment with pickup details
                $shipment->update([
                    'pickup_scheduled' => true,
                    'pickup_confirmation' => $pickupResult['confirmation_number'] ?? null,
                ]);

                Log::info('FedEx pickup scheduled successfully', [
                    'shipment_id' => $shipment->id,
                    'confirmation' => $pickupResult['confirmation_number'] ?? null
                ]);

                // Send pickup confirmation email
                $notificationService = new \App\Services\NotificationService();
                $notificationService->sendPickupScheduled($shipment);

                return redirect()->route('shipment.success', $shipment)
                    ->with('success', 'Pickup has been scheduled successfully!');
            } else {
                Log::error('FedEx pickup scheduling failed', [
                    'shipment_id' => $shipment->id,
                    'error' => $pickupResult['message'] ?? 'Unknown error'
                ]);

                return redirect()->back()->withErrors(['error' => $pickupResult['message'] ?? 'Failed to schedule pickup']);
            }
        } catch (\Exception $e) {
            Log::error('Pickup scheduling error', [
                'shipment_id' => $shipment->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->withErrors(['error' => 'Error scheduling pickup: ' . $e->getMessage()]);
        }
    }

    /**
     * Download shipping label
     */
    public function downloadLabel(Shipment $shipment)
    {
        try {
            // Check for public label file first (web accessible)
            $publicLabelPath = public_path("storage/labels/{$shipment->id}.pdf");

            if (file_exists($publicLabelPath)) {
                return response()->download($publicLabelPath, "shipping-label-{$shipment->tracking_number}.pdf");
            }

            // Fallback to storage directory
            $localLabelPath = storage_path("app/public/labels/{$shipment->id}.pdf");

            if (file_exists($localLabelPath)) {
                return response()->download($localLabelPath, "shipping-label-{$shipment->tracking_number}.pdf");
            }

            // Fallback to FedEx response data
            $fedexResponse = json_decode($shipment->fedex_response, true);

            if (!$fedexResponse || !isset($fedexResponse['label_url'])) {
                return redirect()->back()->withErrors(['error' => 'Shipping label not available']);
            }

            $labelUrl = $fedexResponse['label_url'];

            // If it's a local asset URL, convert to file path
            if (str_contains($labelUrl, asset('storage/labels/'))) {
                $fileName = basename($labelUrl);
                $localPath = storage_path("app/public/labels/{$fileName}");

                if (file_exists($localPath)) {
                    return response()->download($localPath, "shipping-label-{$shipment->tracking_number}.pdf");
                }
            }

            // Download from external URL as fallback
            $labelContent = file_get_contents($labelUrl);

            if ($labelContent === false) {
                return redirect()->back()->withErrors(['error' => 'Unable to download shipping label']);
            }

            return response($labelContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="shipping-label-' . $shipment->tracking_number . '.pdf"');

        } catch (\Exception $e) {
            Log::error('Label download error', [
                'shipment_id' => $shipment->id,
                'exception' => $e->getMessage()
            ]);

            return redirect()->back()->withErrors(['error' => 'Error downloading label: ' . $e->getMessage()]);
        }
    }

    /**
     * View shipping label in browser
     */
    public function viewLabel(Shipment $shipment)
    {
        try {
            // Check for public label file first (web accessible)
            $publicLabelPath = public_path("storage/labels/{$shipment->id}.pdf");

            if (file_exists($publicLabelPath)) {
                return response()->file($publicLabelPath);
            }

            // Fallback to storage directory
            $localLabelPath = storage_path("app/public/labels/{$shipment->id}.pdf");

            if (file_exists($localLabelPath)) {
                return response()->file($localLabelPath);
            }

            // Fallback to FedEx response data
            $fedexResponse = json_decode($shipment->fedex_response, true);

            if (!$fedexResponse || !isset($fedexResponse['label_url'])) {
                return redirect()->back()->withErrors(['error' => 'Shipping label not available']);
            }

            $labelUrl = $fedexResponse['label_url'];

            // If it's a local asset URL, convert to file path
            if (str_contains($labelUrl, asset('storage/labels/'))) {
                $fileName = basename($labelUrl);
                $localPath = storage_path("app/public/labels/{$fileName}");

                if (file_exists($localPath)) {
                    return response()->file($localPath);
                }
            }

            // Download from external URL as fallback
            $labelContent = file_get_contents($labelUrl);

            if ($labelContent === false) {
                return redirect()->back()->withErrors(['error' => 'Unable to load shipping label']);
            }

            return response($labelContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="shipping-label-' . $shipment->tracking_number . '.pdf"');

        } catch (\Exception $e) {
            Log::error('Label view error', [
                'shipment_id' => $shipment->id,
                'exception' => $e->getMessage()
            ]);

            return redirect()->back()->withErrors(['error' => 'Error viewing label: ' . $e->getMessage()]);
        }
    }

    /**
     * Track shipment with FedEx API
     */
    public function trackShipment(Shipment $shipment)
    {
        try {
            if (!$shipment->tracking_number) {
                return redirect()->back()->withErrors(['error' => 'No tracking number available']);
            }

            $trackingInfo = $this->fedexService->trackPackage($shipment->tracking_number);

            return view('shipment.tracking', [
                'shipment' => $shipment,
                'trackingInfo' => $trackingInfo,
            ]);
        } catch (\Exception $e) {
            Log::error('Shipment tracking error', [
                'shipment_id' => $shipment->id,
                'tracking_number' => $shipment->tracking_number,
                'exception' => $e->getMessage()
            ]);

            return redirect()->back()->withErrors(['error' => 'Unable to track shipment: ' . $e->getMessage()]);
        }
    }

    /**
     * Track shipment
     */
    public function track(string $trackingNumber)
    {
        try {
            $fedexService = new \App\Services\FedExService();
            $trackingInfo = $fedexService->trackPackage($trackingNumber);

            return view('shipment.track', [
                'trackingNumber' => $trackingNumber,
                'trackingInfo' => $trackingInfo,
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Unable to track package: ' . $e->getMessage()]);
        }
    }

    /**
     * Get cities for a specific state
     */
    public function getCities(string $state)
    {
        $cities = $this->stateService->getCitiesForState(strtoupper($state));

        return response()->json([
            'cities' => $cities
        ]);
    }

    /**
     * Payment success callback from PayPal
     */
    public function paymentSuccess(Request $request)
    {
        // Log all incoming parameters
        Log::info('Payment success callback received', [
            'all_params' => $request->all()
        ]);

        $shipmentId = $request->input('shipment');
        $token = $request->input('token');
        $payerId = $request->input('PayerID');

        Log::info('Payment success parameters', [
            'shipment_id' => $shipmentId,
            'token_exists' => !empty($token),
            'payer_id_exists' => !empty($payerId)
        ]);

        $shipment = Shipment::find($shipmentId);

        if (!$shipment) {
            Log::error('Payment success: Shipment not found', [
                'shipment_id' => $shipmentId,
                'request_params' => $request->all()
            ]);
            return redirect()->route('home')->withErrors(['error' => 'Shipment not found']);
        }

        try {
            // Get the transaction
            $transaction = $shipment->paymentTransactions()->latest()->first();

            if (!$transaction) {
                Log::error('Payment success: Transaction not found', [
                    'shipment_id' => $shipment->id
                ]);
                return redirect()->route('home')->withErrors(['error' => 'Payment transaction not found']);
            }

            // If we have a PayPal token and PayerID, capture the payment
            if ($token && $payerId && $transaction->status === 'pending') {
                Log::info('Capturing PayPal payment', [
                    'shipment_id' => $shipment->id,
                    'token' => $token,
                    'payer_id' => $payerId,
                    'order_id' => $transaction->order_id
                ]);

                // Store token and PayerID in transaction
                $transaction->update([
                    'gateway_data' => json_encode([
                        'token' => $token,
                        'payer_id' => $payerId
                    ])
                ]);

                // Real PayPal capture
                $paypalService = new \App\Services\PayPalService();
                $captureResult = $paypalService->captureOrder($token); // Use token as order_id

                if ($captureResult['success']) {
                    $transaction->update([
                        'status' => 'completed',
                        'transaction_id' => $captureResult['transaction_id'],
                        'gateway_response' => json_encode($captureResult),
                        'paid_at' => now(),
                    ]);

                    $shipment->update(['status' => 'paid']);

                    Log::info('Payment captured successfully', [
                        'shipment_id' => $shipment->id,
                        'transaction_id' => $captureResult['transaction_id']
                    ]);

                    // Process shipment after payment
                    $shipmentProcessed = $this->processShipmentAfterPayment($shipment);

                    // Redirect to success page
                    return redirect()->route('shipment.success', ['shipment' => $shipment]);
                } else {
                    $transaction->update([
                        'status' => 'failed',
                        'error_response' => json_encode($captureResult),
                    ]);

                    Log::error('Payment capture failed', [
                        'shipment_id' => $shipment->id,
                        'result' => $captureResult
                    ]);

                    return redirect()->route('shipment.checkout', $shipment)->withErrors(['error' => 'Payment failed: ' . ($captureResult['message'] ?? 'Unknown error')]);
                }
            } elseif ($transaction->status === 'completed') {
                // Payment was already completed, just show success page
                Log::info('Payment already completed, showing success page', [
                    'shipment_id' => $shipment->id
                ]);

                return redirect()->route('shipment.success', ['shipment' => $shipment]);
            } else {
                // No token/PayerID but payment not completed
                Log::warning('Payment success: Missing token/PayerID or payment not pending', [
                    'shipment_id' => $shipment->id,
                    'token_exists' => !empty($token),
                    'payer_id_exists' => !empty($payerId),
                    'transaction_status' => $transaction->status
                ]);
            }

            return redirect()->route('shipment.success', ['shipment' => $shipment]);
        } catch (\Exception $e) {
            Log::error('Payment success error', [
                'shipment_id' => $shipment->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('shipment.checkout', $shipment)->withErrors(['error' => 'Payment processing error: ' . $e->getMessage()]);
        }
    }

    /**
     * Payment cancelled page
     */
    public function paymentCancel()
    {
        return view('shipment.cancel');
    }

    /**
     * @return array<string, mixed>
     */
    private function createFormDefaultsFromShipment(Shipment $shipment): array
    {
        return [
            'sender_full_name' => $shipment->sender_full_name,
            'sender_email' => $shipment->sender_email,
            'sender_phone' => $shipment->sender_phone,
            'sender_address_line' => $shipment->sender_address_line,
            'sender_city' => $shipment->sender_city,
            'sender_zipcode' => $shipment->sender_zipcode,
            'recipient_name' => $shipment->recipient_name,
            'recipient_phone' => $shipment->recipient_phone,
            'recipient_address' => $shipment->recipient_address,
            'recipient_city' => $shipment->recipient_city,
            'recipient_zip' => $shipment->recipient_postal_code,
            'bag_type' => $shipment->bag_type,
            'number_of_bags' => $shipment->number_of_bags,
            'declared_value' => $shipment->declared_value,
            'package_description' => $shipment->package_description,
            'package_weight' => $shipment->package_weight,
            'package_length' => $shipment->package_length,
            'package_width' => $shipment->package_width,
            'package_height' => $shipment->package_height,
            'service_type' => $shipment->service_type,
            'pickup_type' => $shipment->pickup_type ?? 'DROPOFF',
            'delivery_method' => $shipment->delivery_method ?? 'dropoff',
        ];
    }

    private function rememberShipmentCreateStates(Shipment $shipment): void
    {
        if ($shipment->origin_state && $shipment->destination_state) {
            session()->put('shipment_create_context', [
                'origin_state' => $shipment->origin_state,
                'destination_state' => $shipment->destination_state,
            ]);
        }
    }

    private function defaultPreferredShipDateForQuote(): string
    {
        $d = \Carbon\Carbon::today();
        while ($d->isWeekend()) {
            $d->addDay();
        }

        return $d->format('Y-m-d');
    }

    private function applyPickupPreference(Shipment $shipment, string $pickupType): void
    {
        $deliveryMethod = $pickupType === 'PICKUP' ? 'pickup' : 'dropoff';

        $updates = [
            'pickup_type' => $pickupType,
            'delivery_method' => $deliveryMethod,
        ];

        if ($pickupType === 'DROPOFF') {
            $updates = array_merge($updates, [
                'pickup_address' => null,
                'pickup_city' => null,
                'pickup_postal_code' => null,
                'pickup_date' => null,
                'pickup_time_slot' => null,
                'pickup_instructions' => null,
                'pickup_ready_time' => null,
                'pickup_close_time' => null,
            ]);
            session()->forget('pickup_availability');
        }

        $shipment->update($updates);
    }

    private function pickupDetailsIncomplete(Shipment $shipment): bool
    {
        return empty(trim((string) $shipment->pickup_address))
            || empty(trim((string) $shipment->pickup_city))
            || empty(trim((string) $shipment->pickup_postal_code))
            || empty($shipment->pickup_date)
            || empty(trim((string) $shipment->pickup_ready_time))
            || empty(trim((string) $shipment->pickup_close_time));
    }
}
