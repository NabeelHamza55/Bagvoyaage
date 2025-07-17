<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Shipment;
use App\Services\NotificationService;
use App\Services\StateService;
use App\Services\FedExServiceFixed;
use Illuminate\Support\Facades\Log;

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
        return view('shipment.index', compact('states'));
    }

    /**
     * Show the shipment creation form (GET request)
     */
    public function showCreateForm(Request $request)
    {
        // Redirect to homepage if no states are in the session
        if (!$request->has('origin_state') || !$request->has('destination_state')) {
            return redirect()->route('home')
                ->withErrors(['error' => 'Please select origin and destination states first']);
        }

        $request->validate([
            'origin_state' => 'required|string|size:2',
            'destination_state' => 'required|string|size:2',
        ]);

        $states = $this->fedexService->getAvailableStates();

        return view('shipment.create', [
            'states' => $states,
            'origin_state' => $request->origin_state,
            'destination_state' => $request->destination_state,
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
            // Pickup/Delivery Method
            'pickup_type' => 'required|in:PICKUP,DROPOFF',
            'delivery_method' => 'required|in:pickup,dropoff',
            'packaging_type' => 'required|string|max:50',
            'service_type' => 'nullable|string|max:50',
            // Pickup Address (conditional)
            'pickup_address' => 'nullable|string|max:500',
            'pickup_city' => 'nullable|string|max:100',
            'pickup_city_custom' => 'nullable|string|max:100',
            'pickup_zip' => 'nullable|string|max:10',
            // Pickup Scheduling (conditional)
            'pickup_date' => 'nullable|date|after:today',
            'pickup_ready_time' => 'nullable|date_format:H:i',
            'pickup_close_time' => 'nullable|date_format:H:i|after:pickup_ready_time',
            'pickup_instructions' => 'nullable|string|max:500',
            // Recipient Information (Required)
            'recipient_name' => 'required|string|max:255',
            'recipient_phone' => 'required|string|max:20',
            'recipient_address' => 'required|string|max:500',
            'recipient_city' => 'required|string|max:100',
            'recipient_city_custom' => 'nullable|string|max:100',
            'recipient_zip' => 'required|string|max:10',
            // Package Information (Required)
            'package_length' => 'required|numeric|min:0.01|max:108',
            'package_width' => 'required|numeric|min:0.01|max:70',
            'package_height' => 'required|numeric|min:0.01|max:70',
            'package_weight' => 'required|numeric|min:0.01|max:150',
            'weight_unit' => 'required|in:LB,KG',
            'dimension_unit' => 'required|in:IN,CM',
            'package_description' => 'required|string|max:500',
            'declared_value' => 'required|numeric|min:1.00|max:50000.00',
            'currency_code' => 'required|string|size:3',
            // Shipping Preferences
            'delivery_type' => 'required|in:standard,express,overnight',
            'preferred_ship_date' => 'required|date|after_or_equal:today',
        ]);

        // Additional validation for pickup fields
        if ($validated['pickup_type'] === 'PICKUP') {
            $request->validate([
                'pickup_address' => 'required|string|max:500',
                'pickup_city' => 'required|string|max:100',
                'pickup_city_custom' => 'nullable|string|max:100',
                'pickup_zip' => 'required|string|max:10',
                'pickup_date' => 'required|date|after:today',
                'pickup_ready_time' => 'required|date_format:H:i',
                'pickup_close_time' => 'required|date_format:H:i|after:pickup_ready_time',
            ]);
        }

        // Create shipment data without ZIP validation
        $shipmentData = [
            ...$validated,
            'pickup_state' => $validated['origin_state'],
            'pickup_city' => $validated['pickup_city'] === 'other' ? $validated['pickup_city_custom'] : $validated['pickup_city'],
            'pickup_postal_code' => $validated['pickup_zip'] ?? null,
            'recipient_state' => $validated['destination_state'],
            'recipient_city' => $validated['recipient_city'] === 'other' ? $validated['recipient_city_custom'] : $validated['recipient_city'],
            'recipient_postal_code' => $validated['recipient_zip'],
            'status' => 'pending',
            'origin_country' => 'US',
            'destination_country' => 'US',
        ];

        $shipment = Shipment::create($shipmentData);

        try {
            // Get FedEx rates
            $rates = $this->fedexService->getRates($shipment);
            $shipment->update(['status' => 'quote_received']);

            // Check if we got any rates back
            if (empty($rates)) {
                return view('shipment.quote', [
                    'shipment' => $shipment,
                    'rates' => [],
                    'states' => $this->fedexService->getAvailableStates(),
                    'error' => 'No shipping rates available for this package and destination.'
                ]);
            }

            return view('shipment.quote', [
                'shipment' => $shipment,
                'rates' => $rates,
                'states' => $this->fedexService->getAvailableStates(),
            ]);
        } catch (\Exception $e) {
            // Log the error
            Log::error('FedEx Rate Error in Controller: ' . $e->getMessage(), [
                'shipment_id' => $shipment->id,
                'package_dimensions' => [
                    'length' => $shipment->package_length,
                    'width' => $shipment->package_width,
                    'height' => $shipment->package_height,
                    'weight' => $shipment->package_weight
                ]
            ]);

            // Extract error message for user
            $errorMessage = 'Unable to get shipping rates';

            // Check if it's a package dimension error
            if (strpos($e->getMessage(), 'PACKAGE.DIMENSIONS.EXCEEDED') !== false) {
                $errorMessage = 'Package dimensions or weight exceed FedEx limits. Please reduce the size or weight of your package.';
            }
            // Check if it's a rate request type error
            else if (strpos($e->getMessage(), 'RATEREQUESTTYPE.REQUIRED') !== false) {
                $errorMessage = 'There was an issue with the shipping rate request. Please try again.';
            }
            // General API error
            else if (strpos($e->getMessage(), 'FedX API') !== false) {
                $errorMessage = 'FedX shipping service is temporarily unavailable. Please try again later.';
            }

            return back()->withErrors(['error' => $errorMessage])->withInput();
        }
    }

    /**
     * Display checkout page
     */
    public function checkout(Request $request, Shipment $shipment)
    {
        $selectedRateId = $request->input('selected_rate');

        if (!$selectedRateId) {
            return redirect()->back()->withErrors(['error' => 'Please select a shipping rate']);
        }

        // Mark the selected rate
        $shipment->rates()->update(['is_selected' => false]);
        $selectedRate = $shipment->rates()->find($selectedRateId);

        if (!$selectedRate) {
            return redirect()->back()->withErrors(['error' => 'Invalid rate selected']);
        }

        $selectedRate->update(['is_selected' => true]);
        $shipment->update(['status' => 'payment_pending']);

        return view('shipment.checkout', [
            'shipment' => $shipment,
            'selectedRate' => $selectedRate,
            'states' => $this->stateService->getStates(),
        ]);
    }

    /**
     * Process PayPal payment
     */
    public function processPayment(Request $request, Shipment $shipment)
    {
        $selectedRate = $shipment->selectedRate;

        if (!$selectedRate) {
            return redirect()->back()->withErrors(['error' => 'No rate selected']);
        }

        try {
            // Verify PayPal credentials are configured
            if (empty(config('services.paypal.client_id')) || empty(config('services.paypal.client_secret'))) {
                return redirect()->back()->withErrors([
                    'error' => 'PayPal API credentials are not configured. Please contact support.'
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
                $shipment->save();

                // Create shipment tags after successful shipment creation
                Log::info('Creating shipment tags after successful shipment creation', [
                    'shipment_id' => $shipment->id,
                    'tracking_number' => $response['tracking_number'] ?? null
                ]);

                $tagResult = $this->fedexService->createShipmentTags($shipment, $response);

                if ($tagResult['success']) {
                    Log::info('Shipment tags created successfully', [
                        'shipment_id' => $shipment->id,
                        'tag_url' => $tagResult['tag_url'] ?? null
                    ]);

                    // Update shipment with tag information (store in fedex_response since label_url column doesn't exist)
                    $currentFedexResponse = json_decode($shipment->fedex_response, true) ?: [];
                    $currentFedexResponse['label_url'] = $tagResult['tag_url'] ?? null;
                    $shipment->fedex_response = json_encode($currentFedexResponse);
                    $shipment->status = 'label_generated';
                    $shipment->save();
                } else {
                    Log::error('Shipment tag creation failed', [
                        'shipment_id' => $shipment->id,
                        'error' => $tagResult['message'] ?? 'Unknown error'
                    ]);
                }

                // Debug pickup scheduling conditions
                Log::info('Checking pickup scheduling conditions', [
                    'shipment_id' => $shipment->id,
                    'pickup_type' => $shipment->pickup_type,
                    'pickup_scheduled' => $shipment->pickup_scheduled,
                    'condition_met' => ($shipment->pickup_type == 'PICKUP' && !$shipment->pickup_scheduled)
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
                        $shipment->pickup_date = $pickupResult['scheduled_date'] ?? $shipment->preferred_ship_date->format('Y-m-d');
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
                        Log::error('FedEx pickup scheduling failed after shipment creation', [
                            'shipment_id' => $shipment->id,
                            'error' => $pickupResult['message'] ?? 'Unknown error',
                            'error_code' => $pickupResult['error_code'] ?? null
                        ]);

                        // Still mark as scheduled but with error note
                        $shipment->pickup_scheduled = true;
                        $shipment->pickup_confirmation = 'FAILED-' . time();
                        // Log the error details since pickup_notes column doesn't exist
                        Log::error('Pickup scheduling failed - error details', [
                            'shipment_id' => $shipment->id,
                            'error' => $pickupResult['message'] ?? 'Unknown error'
                        ]);
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

        return view('shipment.schedule-pickup', [
            'shipment' => $shipment,
            'availability' => $availabilityResult,
            'cutoff_time' => $availabilityResult['cutoff_time'],
            'access_time' => $availabilityResult['access_time']
        ]);
    }

    /**
     * Schedule a pickup with FedEx
     */
    public function schedulePickup(Request $request, Shipment $shipment)
    {
        // Validate pickup details if needed
        $request->validate([
            'confirm_pickup' => 'required|accepted',
            'pickup_date' => 'nullable|date|after_or_equal:today',
        ]);

        try {
            // If custom pickup date provided, update the shipment
            if ($request->has('pickup_date')) {
                $shipment->update([
                    'preferred_ship_date' => $request->pickup_date
                ]);
            }

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
            // Get FedEx response data
            $fedexResponse = json_decode($shipment->fedex_response, true);

            if (!$fedexResponse || !isset($fedexResponse['label_url'])) {
                return redirect()->back()->withErrors(['error' => 'Shipping label not available']);
            }

            $labelUrl = $fedexResponse['label_url'];

            // Download and return the label
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
            } else if ($transaction->status === 'completed') {
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
}
