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

// Utility routes
Route::get('/cities/{state}', [ShipmentController::class, 'getCities'])->name('cities.by.state');
