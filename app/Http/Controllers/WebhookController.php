<?php

namespace App\Http\Controllers;

use App\Services\PayPalService;
use App\Models\Shipment;
use App\Models\PaymentTransaction;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    protected $paypalService;

    public function __construct(PayPalService $paypalService)
    {
        $this->paypalService = $paypalService;
    }

    /**
     * Handle PayPal webhook notifications
     */
    public function paypal(Request $request)
    {
        try {
            // Get the raw payload
            $payload = $request->getContent();

            // Get PayPal headers
            $headers = [
                'PAYPAL-AUTH-ALGO' => $request->header('PAYPAL-AUTH-ALGO'),
                'PAYPAL-TRANSMISSION-ID' => $request->header('PAYPAL-TRANSMISSION-ID'),
                'PAYPAL-CERT-ID' => $request->header('PAYPAL-CERT-ID'),
                'PAYPAL-TRANSMISSION-SIG' => $request->header('PAYPAL-TRANSMISSION-SIG'),
                'PAYPAL-TRANSMISSION-TIME' => $request->header('PAYPAL-TRANSMISSION-TIME'),
            ];

            // Log the webhook attempt
            Log::info('PayPal webhook received', [
                'headers' => $headers,
                'payload_size' => strlen($payload)
            ]);

            // Validate the webhook signature
            if (!$this->paypalService->validateWebhook($payload, $headers)) {
                Log::warning('PayPal webhook validation failed', [
                    'transmission_id' => $headers['PAYPAL-TRANSMISSION-ID'] ?? 'unknown',
                    'ip_address' => $request->ip()
                ]);

                return response()->json(['error' => 'Invalid webhook signature'], 401);
            }

            // Parse the event data
            $eventData = json_decode($payload, true);

            if (!$eventData) {
                Log::error('PayPal webhook: Invalid JSON payload');
                return response()->json(['error' => 'Invalid JSON payload'], 400);
            }

            // Process the webhook event
            $processed = $this->paypalService->processWebhookEvent($eventData);

            if ($processed) {
                Log::info('PayPal webhook processed successfully', [
                    'event_type' => $eventData['event_type'] ?? 'unknown',
                    'event_id' => $eventData['id'] ?? 'unknown'
                ]);

                return response()->json(['status' => 'success'], 200);
            } else {
                Log::warning('PayPal webhook processing failed', [
                    'event_type' => $eventData['event_type'] ?? 'unknown',
                    'event_id' => $eventData['id'] ?? 'unknown'
                ]);

                return response()->json(['error' => 'Processing failed'], 500);
            }

        } catch (Exception $e) {
            Log::error('PayPal webhook error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Handle test webhook (for development/testing)
     */
    public function test(Request $request)
    {
        try {
            Log::info('Test webhook received', [
                'data' => $request->all(),
                'headers' => $request->headers->all()
            ]);

            // Simulate webhook processing
            $eventData = $request->all();

            if (isset($eventData['event_type'])) {
                $processed = $this->paypalService->processWebhookEvent($eventData);

                return response()->json([
                    'status' => $processed ? 'success' : 'failed',
                    'message' => $processed ? 'Event processed' : 'Event processing failed'
                ], $processed ? 200 : 500);
            }

            return response()->json(['status' => 'success', 'message' => 'Test webhook received'], 200);

        } catch (Exception $e) {
            Log::error('Test webhook error: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get webhook status/health check
     */
    public function status()
    {
        try {
            return response()->json([
                'status' => 'operational',
                'timestamp' => now()->toISOString(),
                'version' => '1.0'
            ], 200);

        } catch (Exception $e) {
            Log::error('Webhook status check error: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
}
