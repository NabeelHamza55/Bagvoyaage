<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShipmentController;

// Homepage with origin/destination form
Route::get('/', [ShipmentController::class, 'index'])->name('home');

// Detailed shipment form
Route::get('/shipment/create', [ShipmentController::class, 'showCreateForm'])->name('shipment.form');
Route::post('/shipment/create', [ShipmentController::class, 'create'])->name('shipment.create');

// Process detailed shipment form and get FedEx rates
Route::post('/shipment/quote', [ShipmentController::class, 'getQuote'])->name('shipment.quote');

// Checkout page
Route::get('/shipment/{shipment}/checkout', [ShipmentController::class, 'checkout'])->name('shipment.checkout');

// Payment processing
Route::post('/shipment/{shipment}/payment', [ShipmentController::class, 'processPayment'])->name('shipment.payment');

// Payment success/failure callbacks
Route::get('/payment/success', [ShipmentController::class, 'paymentSuccess'])->name('payment.success');
Route::get('/payment/cancel', [ShipmentController::class, 'paymentCancel'])->name('payment.cancel');

// Shipment success page with all management options
Route::get('/shipment/{shipment}/success', [ShipmentController::class, 'success'])->name('shipment.success');

// Pickup scheduling
Route::get('/shipment/{shipment}/pickup', [ShipmentController::class, 'showPickupForm'])->name('shipment.pickup.form');
Route::post('/shipment/{shipment}/pickup', [ShipmentController::class, 'schedulePickup'])->name('shipment.pickup.schedule');

// Label download
Route::get('/shipment/{shipment}/label', [ShipmentController::class, 'downloadLabel'])->name('shipment.label.download');

// Shipment tracking
Route::get('/shipment/{shipment}/tracking', [ShipmentController::class, 'trackShipment'])->name('shipment.tracking');
Route::get('/track/{tracking_number}', [ShipmentController::class, 'track'])->name('shipment.track');

// Webhook routes
Route::post('/webhook/paypal', [App\Http\Controllers\WebhookController::class, 'paypal'])->name('webhook.paypal');
Route::post('/webhook/test', [App\Http\Controllers\WebhookController::class, 'test'])->name('webhook.test');
Route::get('/webhook/status', [App\Http\Controllers\WebhookController::class, 'status'])->name('webhook.status');

// Test route to simulate payment completion for local testing
Route::get('/test/payment-complete/{shipment}', function(\App\Models\Shipment $shipment) {
    try {
        // Get the selected rate for amount calculation
        $selectedRate = $shipment->selectedRate;

        if (!$selectedRate) {
            return response()->json([
                'error' => 'No rate selected for this shipment',
                'shipment_id' => $shipment->id
            ], 400);
        }

        // Create a test transaction to simulate payment completion
        $transaction = \App\Models\PaymentTransaction::create([
            'shipment_id' => $shipment->id,
            'custom_id' => uniqid('test_'),
            'transaction_id' => 'TEST_TXN_' . time(),
            'order_id' => 'TEST_ORDER_' . time(),
            'amount' => $selectedRate->total_rate,
            'currency' => $selectedRate->currency ?? 'USD',
            'status' => 'pending',
            'payment_method' => 'paypal',
        ]);

        // Simulate PayPal success redirect
        $successUrl = route('payment.success', [
            'shipment' => $shipment->id,
            'token' => $transaction->order_id,
            'PayerID' => 'TEST_PAYER_' . time()
        ]);

        return response()->json([
            'status' => 'test_setup_complete',
            'message' => 'Test transaction created, click the link to simulate payment success',
            'shipment_id' => $shipment->id,
            'transaction_id' => $transaction->id,
            'test_amount' => $selectedRate->total_rate,
            'success_url' => $successUrl,
            'shipment_details_url' => route('shipment.success', $shipment),
            'html_link' => '<a href="' . $successUrl . '" target="_blank">Click here to simulate payment success</a>'
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Failed to create test transaction: ' . $e->getMessage(),
            'shipment_id' => $shipment->id ?? null
        ], 500);
    }
})->name('test.payment.complete');

// Utility routes
Route::get('/cities/{state}', [ShipmentController::class, 'getCities'])->name('cities.by.state');

// Development/Testing routes
Route::get('/test/paypal', function() {
    try {
        $paypalService = new \App\Services\PayPalService();
        // Test authentication only
        $response = \Illuminate\Support\Facades\Http::withBasicAuth(
            config('services.paypal.client_id'),
            config('services.paypal.client_secret')
        )->asForm()->post(
            (config('services.paypal.mode') === 'live' ? 'https://api.paypal.com' : 'https://api.sandbox.paypal.com') . '/v1/oauth2/token',
            ['grant_type' => 'client_credentials']
        );

        if ($response->successful()) {
            return response()->json([
                'success' => true,
                'message' => 'PayPal API authentication successful',
                'token_type' => $response->json()['token_type'] ?? 'unknown'
            ]);
        } else {
            return response()->json([
                'success' => false,
                'error' => 'Authentication failed',
                'details' => $response->json()
            ], 500);
        }
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

Route::get('/test/paypal-redirect', function() {
    try {
        // Show the current PayPal return URL configuration
        $returnUrl = route('payment.success', ['shipment' => 1]);
        $cancelUrl = route('payment.cancel');

        return response()->json([
            'success' => true,
            'return_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
            'routes' => [
                'success_route' => route('payment.success', ['shipment' => 1, 'token' => 'test_token', 'PayerID' => 'test_payer']),
                'cancel_route' => route('payment.cancel')
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// Test route to simulate PayPal payment success
Route::get('/test/payment-success', function() {
    // Find a valid shipment
    $shipment = \App\Models\Shipment::first();

    if (!$shipment) {
        return response()->json(['error' => 'No shipment found']);
    }

    // Create a test transaction
    $transaction = \App\Models\PaymentTransaction::create([
        'shipment_id' => $shipment->id,
        'custom_id' => uniqid('test_'),
        'order_id' => 'TEST_ORDER_' . time(),
        'amount' => 25.99,
        'currency' => 'USD',
        'status' => 'pending',
        'payment_method' => 'paypal',
    ]);

    // Generate success URL
    $successUrl = route('payment.success', [
        'shipment' => $shipment->id,
        'token' => $transaction->order_id,
        'PayerID' => 'TEST_PAYER_' . time()
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Test transaction created',
        'shipment_id' => $shipment->id,
        'transaction_id' => $transaction->id,
        'success_url' => $successUrl,
        'html_link' => '<a href="' . $successUrl . '">Click here to simulate payment success</a>'
    ]);
});
