<?php

namespace App\Services;

use App\Models\PaymentTransaction;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class PayPalService
{
    private $baseUrl;
    private $clientId;
    private $clientSecret;
    private $accessToken;

    public function __construct()
    {
        // Read mode from config (which reads from env PAYPAL_MODE)
        // Default to 'sandbox' if not set
        $mode = strtolower(config('services.paypal.mode', 'sandbox'));

        // Set base URL based on mode (URLs stay in code as they should)
        $this->baseUrl = $mode === 'live'
            ? 'https://api.paypal.com'
            : 'https://api.sandbox.paypal.com';

        $this->clientId = config('services.paypal.client_id');
        $this->clientSecret = config('services.paypal.client_secret');

        // Log credentials being used (safely - only show prefix and length)
        $clientIdPrefix = $this->clientId ? substr($this->clientId, 0, 10) . '...' : 'NOT SET';
        $clientSecretPrefix = $this->clientSecret ? substr($this->clientSecret, 0, 10) . '...' : 'NOT SET';

        Log::info('PayPal service initialized - Credentials being used', [
            'mode' => $mode,
            'base_url' => $this->baseUrl,
            'mode_from_env' => env('PAYPAL_MODE', 'not set'),
            'credentials' => [
                'client_id' => [
                    'present' => !empty($this->clientId),
                    'prefix' => $clientIdPrefix,
                    'length' => $this->clientId ? strlen($this->clientId) : 0,
                    'source' => [
                        'env_set' => env('PAYPAL_CLIENT_ID') ? 'yes' : 'no',
                        'config_set' => config('services.paypal.client_id') ? 'yes' : 'no',
                        'env_value_prefix' => env('PAYPAL_CLIENT_ID') ? substr(env('PAYPAL_CLIENT_ID'), 0, 10) . '...' : 'not set',
                        'config_value_prefix' => config('services.paypal.client_id') ? substr(config('services.paypal.client_id'), 0, 10) . '...' : 'not set'
                    ]
                ],
                'client_secret' => [
                    'present' => !empty($this->clientSecret),
                    'prefix' => $clientSecretPrefix,
                    'length' => $this->clientSecret ? strlen($this->clientSecret) : 0,
                    'source' => [
                        'env_set' => env('PAYPAL_CLIENT_SECRET') ? 'yes' : 'no',
                        'config_set' => config('services.paypal.client_secret') ? 'yes' : 'no',
                        'env_value_prefix' => env('PAYPAL_CLIENT_SECRET') ? substr(env('PAYPAL_CLIENT_SECRET'), 0, 10) . '...' : 'not set',
                        'config_value_prefix' => config('services.paypal.client_secret') ? substr(config('services.paypal.client_secret'), 0, 10) . '...' : 'not set'
                    ]
                ]
            ],
            'warning' => (empty($this->clientId) || empty($this->clientSecret))
                ? 'PayPal credentials are missing! Check your .env file for PAYPAL_CLIENT_ID and PAYPAL_CLIENT_SECRET'
                : 'Credentials loaded successfully'
        ]);

        // Warn if credentials are missing
        if (empty($this->clientId) || empty($this->clientSecret)) {
            Log::warning('PayPal credentials are not configured', [
                'base_url' => $this->baseUrl,
                'mode' => $mode,
                'env_paypal_mode' => env('PAYPAL_MODE'),
                'env_paypal_client_id' => env('PAYPAL_CLIENT_ID') ? 'set' : 'not set',
                'env_paypal_client_secret' => env('PAYPAL_CLIENT_SECRET') ? 'set' : 'not set',
                'config_paypal_client_id' => config('services.paypal.client_id') ? 'set' : 'not set',
                'config_paypal_client_secret' => config('services.paypal.client_secret') ? 'set' : 'not set'
            ]);
        }
    }

    /**
     * Get PayPal access token
     */
    private function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        try {
            // Check if credentials are configured
            if (empty($this->clientId) || empty($this->clientSecret)) {
                $errorMsg = 'PayPal API credentials are not configured. ';
                $errorMsg .= 'Please check your .env file for PAYPAL_CLIENT_ID and PAYPAL_CLIENT_SECRET. ';
                $errorMsg .= 'Mode: ' . (strpos($this->baseUrl, 'sandbox') !== false ? 'sandbox' : 'live');

                Log::error('PayPal credentials missing', [
                    'base_url' => $this->baseUrl,
                    'has_client_id' => !empty($this->clientId),
                    'has_client_secret' => !empty($this->clientSecret),
                    'client_id_length' => $this->clientId ? strlen($this->clientId) : 0,
                    'client_secret_length' => $this->clientSecret ? strlen($this->clientSecret) : 0
                ]);

                throw new Exception($errorMsg);
            }

            // Log authentication attempt (without exposing credentials)
            Log::info('Attempting PayPal OAuth authentication', [
                'base_url' => $this->baseUrl,
                'client_id_prefix' => substr($this->clientId, 0, 10) . '...',
                'client_id_length' => strlen($this->clientId),
                'client_secret_length' => strlen($this->clientSecret)
            ]);

            $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
                ->asForm()
                ->post($this->baseUrl . '/v1/oauth2/token', [
                    'grant_type' => 'client_credentials'
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $this->accessToken = $data['access_token'];

                Log::info('PayPal OAuth authentication successful', [
                    'base_url' => $this->baseUrl,
                    'token_type' => $data['token_type'] ?? 'unknown',
                    'expires_in' => $data['expires_in'] ?? 'unknown'
                ]);

                return $this->accessToken;
            }

            // Enhanced error logging
            $statusCode = $response->status();
            $errorData = $response->json();
            $errorMessage = $errorData['error_description'] ?? $errorData['error'] ?? 'Unknown error';
            $errorCode = $errorData['error'] ?? 'UNKNOWN';

            Log::error('PayPal OAuth authentication failed', [
                'base_url' => $this->baseUrl,
                'status_code' => $statusCode,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'response_body' => $response->body(),
                'client_id_prefix' => substr($this->clientId, 0, 10) . '...',
                'client_id_length' => strlen($this->clientId),
                'hint' => $statusCode === 401
                    ? 'Check if credentials match the environment (sandbox vs live). Sandbox credentials only work with sandbox API, live credentials only work with live API.'
                    : 'Check PayPal API status and credentials validity.'
            ]);

            throw new Exception('Failed to get PayPal access token: ' . $errorMessage);
        } catch (Exception $e) {
            Log::error('PayPal OAuth Error: ' . $e->getMessage(), [
                'base_url' => $this->baseUrl,
                'exception_type' => get_class($e)
            ]);
            throw $e;
        }
    }

    /**
     * Create PayPal payment using Orders API
     * This method creates a PayPal order and returns the approval URL
     */
    public function createPayment(PaymentTransaction $transaction): array
    {
        try {
            // Create order first
            $orderResult = $this->createOrder($transaction);

            if (!$orderResult['success']) {
                return $orderResult;
            }

            // Save order ID to transaction
            $transaction->update([
                'order_id' => $orderResult['order_id'],
                'status' => 'pending',
            ]);

            // Return approval URL for redirect
            return [
                'success' => true,
                'order_id' => $orderResult['order_id'],
                'approval_url' => $orderResult['approval_url'],
                'status' => $orderResult['status'],
                'message' => 'Payment initiated successfully',
            ];
        } catch (Exception $e) {
            Log::error('PayPal Payment Error: ' . $e->getMessage(), [
                'transaction_id' => $transaction->id,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency
            ]);

            return [
                'success' => false,
                'message' => 'Payment processing error: ' . $e->getMessage(),
                'error_code' => 'PROCESSING_ERROR',
            ];
        }
    }

    /**
     * Create PayPal order
     */
    public function createOrder(PaymentTransaction $transaction): array
    {
        try {
            $token = $this->getAccessToken();

            // Ensure we have a valid shipment ID
            $shipmentId = $transaction->shipment_id;
            if (empty($shipmentId)) {
                throw new Exception('Missing shipment ID for transaction');
            }

            // Log the order creation attempt
            Log::info('Creating PayPal order', [
                'transaction_id' => $transaction->id,
                'amount' => $transaction->amount,
                'shipment_id' => $shipmentId
            ]);

            // Build absolute URLs with full domain
            $returnUrl = route('payment.success', ['shipment' => $shipmentId], true);
            $cancelUrl = route('payment.cancel', [], true);

            $payload = [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'amount' => [
                            'currency_code' => $transaction->currency,
                            'value' => number_format($transaction->amount, 2, '.', '')
                        ],
                        'description' => 'Shipping service payment',
                        'custom_id' => (string)$transaction->id
                    ]
                ],
                'application_context' => [
                    'cancel_url' => $cancelUrl,
                    'return_url' => $returnUrl,
                    'brand_name' => 'BagVoyage Shipping',
                    'shipping_preference' => 'NO_SHIPPING',
                    'user_action' => 'PAY_NOW',
                    'return_url_description' => 'Return to BagVoyage after payment'
                ]
            ];

            // Log the payload for debugging
            Log::debug('PayPal order creation payload', [
                'transaction_id' => $transaction->id,
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/v2/checkout/orders', $payload);

            if ($response->successful()) {
                $data = $response->json();

                // Log successful response
                Log::info('PayPal order created successfully', [
                    'transaction_id' => $transaction->id,
                    'order_id' => $data['id'],
                    'status' => $data['status'] ?? 'UNKNOWN'
                ]);

                return [
                    'success' => true,
                    'order_id' => $data['id'],
                    'approval_url' => $this->getApprovalUrl($data['links']),
                    'status' => $data['status']
                ];
            }

            $errorData = $response->json();
            $errorMessage = isset($errorData['details'][0])
                ? $errorData['details'][0]['description']
                : ($errorData['message'] ?? 'Unknown error');

            Log::error('PayPal Order Creation Error', [
                'transaction_id' => $transaction->id,
                'status_code' => $response->status(),
                'error' => $errorData
            ]);

            throw new Exception('PayPal Order Creation Error: ' . $errorMessage);
        } catch (Exception $e) {
            Log::error('PayPal Order Creation Error: ' . $e->getMessage(), [
                'transaction_id' => $transaction->id,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency
            ]);

            return [
                'success' => false,
                'message' => 'Order creation error: ' . $e->getMessage(),
                'error_code' => 'ORDER_CREATION_ERROR',
            ];
        }
    }

    /**
     * Capture PayPal order
     */
    public function captureOrder(string $orderId): array
    {
        try {
            $token = $this->getAccessToken();

            // Log the capture attempt
            Log::info('Attempting to capture PayPal order', [
                'order_id' => $orderId
            ]);

            // The order ID could be the token from the return URL
            // PayPal sometimes uses the token as the order ID in the return flow

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation'
            ])->post($this->baseUrl . '/v2/checkout/orders/' . $orderId . '/capture', (object)[]);

            if ($response->successful()) {
                $data = $response->json();

                // Log successful capture
                Log::info('PayPal order captured successfully', [
                    'order_id' => $orderId,
                    'status' => $data['status'] ?? 'UNKNOWN',
                    'response' => $data
                ]);

                // Extract transaction details
                $captureId = $data['purchase_units'][0]['payments']['captures'][0]['id'] ?? $data['id'];
                $amount = $data['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? 0;
                $currency = $data['purchase_units'][0]['payments']['captures'][0]['amount']['currency_code'] ?? 'USD';

                return [
                    'success' => true,
                    'transaction_id' => $captureId,
                    'status' => $data['status'],
                    'amount' => $amount,
                    'currency' => $currency,
                ];
            }

            $errorData = $response->json();
            $errorMessage = isset($errorData['details'][0])
                ? $errorData['details'][0]['description']
                : ($errorData['message'] ?? 'Unknown error');

            Log::error('PayPal Order Capture Error', [
                'order_id' => $orderId,
                'status_code' => $response->status(),
                'error' => $errorData
            ]);

            throw new Exception('PayPal Order Capture Error: ' . $errorMessage);
        } catch (Exception $e) {
            Log::error('PayPal Order Capture Error: ' . $e->getMessage(), [
                'order_id' => $orderId
            ]);

            return [
                'success' => false,
                'message' => 'Order capture error: ' . $e->getMessage(),
                'error_code' => 'ORDER_CAPTURE_ERROR',
            ];
        }
    }

    /**
     * Get approval URL from PayPal response links
     */
    private function getApprovalUrl(array $links): ?string
    {
        // Log the links for debugging
        Log::debug('PayPal links array', [
            'links' => $links
        ]);

        foreach ($links as $link) {
            if (isset($link['rel']) && $link['rel'] === 'approve') {
                Log::info('Found PayPal approval URL', [
                    'url' => $link['href']
                ]);
                return $link['href'];
            }
        }

        Log::warning('No approval URL found in PayPal response');
        return null;
    }

    /**
     * Validate PayPal webhook signature
     */
    public function validateWebhook(string $payload, array $headers): bool
    {
        try {
            // Get required headers
            $authAlgo = $headers['PAYPAL-AUTH-ALGO'] ?? '';
            $transmission = $headers['PAYPAL-TRANSMISSION-ID'] ?? '';
            $certId = $headers['PAYPAL-CERT-ID'] ?? '';
            $transmissionSig = $headers['PAYPAL-TRANSMISSION-SIG'] ?? '';
            $transmissionTime = $headers['PAYPAL-TRANSMISSION-TIME'] ?? '';

            // Validate required headers are present
            if (empty($authAlgo) || empty($transmission) || empty($certId) ||
                empty($transmissionSig) || empty($transmissionTime)) {
                Log::warning('PayPal webhook validation failed: Missing required headers');
                return false;
            }

            // Get webhook ID from config
            $webhookId = config('services.paypal.webhook_id');
            if (empty($webhookId)) {
                Log::error('PayPal webhook ID not configured');
                return false;
            }

            // Create the verification request
            $token = $this->getAccessToken();

            $verificationData = [
                'auth_algo' => $authAlgo,
                'cert_id' => $certId,
                'transmission_id' => $transmission,
                'transmission_sig' => $transmissionSig,
                'transmission_time' => $transmissionTime,
                'webhook_id' => $webhookId,
                'webhook_event' => json_decode($payload, true)
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/v1/notifications/verify-webhook-signature', $verificationData);

            if ($response->successful()) {
                $result = $response->json();
                $isValid = ($result['verification_status'] ?? '') === 'SUCCESS';

                if (!$isValid) {
                    Log::warning('PayPal webhook validation failed: Invalid signature', [
                        'verification_status' => $result['verification_status'] ?? 'unknown',
                        'transmission_id' => $transmission
                    ]);
                }

                return $isValid;
            }

            Log::error('PayPal webhook validation API error: ' . $response->body());
            return false;

        } catch (Exception $e) {
            Log::error('PayPal webhook validation error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Process PayPal webhook event
     */
    public function processWebhookEvent(array $eventData): bool
    {
        try {
            $eventType = $eventData['event_type'] ?? '';
            $resource = $eventData['resource'] ?? [];

            Log::info('Processing PayPal webhook event', [
                'event_type' => $eventType,
                'event_id' => $eventData['id'] ?? '',
                'resource_id' => $resource['id'] ?? ''
            ]);

            switch ($eventType) {
                case 'PAYMENT.CAPTURE.COMPLETED':
                    return $this->handlePaymentCaptureCompleted($resource);

                case 'PAYMENT.CAPTURE.DENIED':
                    return $this->handlePaymentCaptureDenied($resource);

                case 'PAYMENT.CAPTURE.REFUNDED':
                    return $this->handlePaymentCaptureRefunded($resource);

                case 'CHECKOUT.ORDER.APPROVED':
                    return $this->handleOrderApproved($resource);

                case 'CHECKOUT.ORDER.COMPLETED':
                    return $this->handleOrderCompleted($resource);

                default:
                    Log::info('Unhandled PayPal webhook event type: ' . $eventType);
                    return true; // Don't fail for unhandled events
            }

        } catch (Exception $e) {
            Log::error('PayPal webhook processing error: ' . $e->getMessage(), [
                'event_data' => $eventData
            ]);
            return false;
        }
    }

    /**
     * Handle payment capture completed webhook
     */
    private function handlePaymentCaptureCompleted(array $resource): bool
    {
        try {
            $captureId = $resource['id'] ?? '';
            $amount = $resource['amount']['value'] ?? 0;
            $currency = $resource['amount']['currency_code'] ?? 'USD';
            $status = $resource['status'] ?? '';

            // Find the payment transaction by capture ID or custom ID
            $customId = $resource['custom_id'] ?? '';
            $transaction = null;

            if ($customId) {
                $transaction = PaymentTransaction::where('custom_id', $customId)->first();
            }

            if (!$transaction && $captureId) {
                $transaction = PaymentTransaction::where('transaction_id', $captureId)->first();
            }

            if ($transaction) {
                $transaction->update([
                    'status' => 'completed',
                    'transaction_id' => $captureId,
                    'amount' => $amount,
                    'currency' => $currency,
                    'gateway_response' => json_encode($resource),
                    'updated_at' => now()
                ]);

                // Update shipment status and process shipment (create label + schedule pickup)
                if ($transaction->shipment) {
                    $shipment = $transaction->shipment;
                    $shipment->update([
                        'status' => 'paid',
                        'updated_at' => now()
                    ]);

                    Log::info('Webhook: Processing shipment after payment capture', [
                        'shipment_id' => $shipment->id,
                        'transaction_id' => $transaction->id
                    ]);

                    // Trigger shipment processing (label + pickup)
                    try {
                        $fedexService = new \App\Services\FedExServiceFixed();
                        
                        // Create shipment with FedEx
                        $response = $fedexService->createShipment($shipment);

                        if ($response['success']) {
                            $shipment->tracking_number = $response['tracking_number'] ?? null;
                            $shipment->status = 'shipment_created';
                            $shipment->fedex_response = json_encode($response);
                            
                            // Update preferred_ship_date if it was adjusted
                            if (!empty($response['actual_ship_date'])) {
                                $shipment->preferred_ship_date = $response['actual_ship_date'];
                            }
                            
                            $shipment->save();

                            // Create shipment tags (label)
                            $tagResult = $fedexService->createShipmentTags($shipment, $response);
                            if ($tagResult['success']) {
                                $labelBase64 = $tagResult['label_base64'] ?? null;
                                $currentFedexResponse = json_decode($shipment->fedex_response, true) ?: [];

                                if ($labelBase64) {
                                    $filePath = storage_path("app/public/labels/{$shipment->id}.pdf");
                                    file_put_contents($filePath, base64_decode($labelBase64));
                                    $currentFedexResponse['label_url'] = asset("storage/labels/{$shipment->id}.pdf");
                                }

                                $shipment->fedex_response = json_encode($currentFedexResponse);
                                $shipment->status = 'label_generated';
                                $shipment->save();

                                // Send admin notification
                                $notificationService = new \App\Services\NotificationService();
                                $publicLabelPath = public_path("storage/labels/{$shipment->id}.pdf");
                                if (file_exists($publicLabelPath)) {
                                    $notificationService->sendAdminNewOrderNotification($shipment, $publicLabelPath);
                                }
                            }

                            // Schedule pickup if needed
                            if ($shipment->pickup_type == 'PICKUP' && !$shipment->pickup_scheduled) {
                                $pickupResult = $fedexService->schedulePickup($shipment);

                                if ($pickupResult['success']) {
                                    $shipment->pickup_scheduled = true;
                                    $shipment->pickup_confirmation = $pickupResult['confirmation_number'] ?? null;
                                    $shipment->pickup_date = $pickupResult['actual_pickup_date'] ?? $pickupResult['scheduled_date'] ?? null;
                                    
                                    // Update preferred_ship_date if it was adjusted
                                    if (!empty($pickupResult['actual_ship_date'])) {
                                        $shipment->preferred_ship_date = $pickupResult['actual_ship_date'];
                                    }
                                    
                                    $shipment->status = 'pickup_scheduled';
                                    $shipment->save();

                                    Log::info('Webhook: Pickup scheduled after payment', [
                                        'shipment_id' => $shipment->id,
                                        'confirmation_number' => $pickupResult['confirmation_number'],
                                        'actual_pickup_date' => $pickupResult['actual_pickup_date'] ?? null,
                                        'actual_ship_date' => $pickupResult['actual_ship_date'] ?? null
                                    ]);

                                    // Send pickup confirmation email
                                    $notificationService = new \App\Services\NotificationService();
                                    $notificationService->sendPickupScheduled($shipment);
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Webhook: Error processing shipment after payment', [
                            'shipment_id' => $shipment->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                Log::info('Payment capture completed processed', [
                    'transaction_id' => $transaction->id,
                    'capture_id' => $captureId,
                    'amount' => $amount
                ]);

                return true;
            }

            Log::warning('Payment transaction not found for capture completed webhook', [
                'capture_id' => $captureId,
                'custom_id' => $customId
            ]);

            return false;

        } catch (Exception $e) {
            Log::error('Error handling payment capture completed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Handle payment capture denied webhook
     */
    private function handlePaymentCaptureDenied(array $resource): bool
    {
        try {
            $captureId = $resource['id'] ?? '';
            $customId = $resource['custom_id'] ?? '';

            $transaction = null;
            if ($customId) {
                $transaction = PaymentTransaction::where('custom_id', $customId)->first();
            }

            if (!$transaction && $captureId) {
                $transaction = PaymentTransaction::where('transaction_id', $captureId)->first();
            }

            if ($transaction) {
                $transaction->update([
                    'status' => 'failed',
                    'gateway_response' => json_encode($resource),
                    'updated_at' => now()
                ]);

                // Update shipment status
                if ($transaction->shipment) {
                    $transaction->shipment->update([
                        'status' => 'payment_failed',
                        'updated_at' => now()
                    ]);
                }

                Log::info('Payment capture denied processed', [
                    'transaction_id' => $transaction->id,
                    'capture_id' => $captureId
                ]);

                return true;
            }

            Log::warning('Payment transaction not found for capture denied webhook', [
                'capture_id' => $captureId,
                'custom_id' => $customId
            ]);

            return false;

        } catch (Exception $e) {
            Log::error('Error handling payment capture denied: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Handle payment capture refunded webhook
     */
    private function handlePaymentCaptureRefunded(array $resource): bool
    {
        try {
            $refundId = $resource['id'] ?? '';
            $amount = $resource['amount']['value'] ?? 0;
            $currency = $resource['amount']['currency_code'] ?? 'USD';

            // Find the original transaction
            $captureId = $resource['links'][0]['href'] ?? '';
            $captureId = basename($captureId); // Extract capture ID from URL

            $transaction = PaymentTransaction::where('transaction_id', $captureId)->first();

            if ($transaction) {
                $transaction->update([
                    'status' => 'refunded',
                    'gateway_response' => json_encode($resource),
                    'updated_at' => now()
                ]);

                // Update shipment status
                if ($transaction->shipment) {
                    $transaction->shipment->update([
                        'status' => 'refunded',
                        'updated_at' => now()
                    ]);
                }

                Log::info('Payment refund processed', [
                    'transaction_id' => $transaction->id,
                    'refund_id' => $refundId,
                    'amount' => $amount
                ]);

                return true;
            }

            Log::warning('Payment transaction not found for refund webhook', [
                'refund_id' => $refundId,
                'capture_id' => $captureId
            ]);

            return false;

        } catch (Exception $e) {
            Log::error('Error handling payment refund: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Handle order approved webhook
     */
    private function handleOrderApproved(array $resource): bool
    {
        try {
            $orderId = $resource['id'] ?? '';
            $status = $resource['status'] ?? '';

            // Find transaction by order ID
            $transaction = PaymentTransaction::where('order_id', $orderId)->first();

            if ($transaction) {
                $transaction->update([
                    'status' => 'approved',
                    'gateway_response' => json_encode($resource),
                    'updated_at' => now()
                ]);

                Log::info('Order approved processed', [
                    'transaction_id' => $transaction->id,
                    'order_id' => $orderId
                ]);

                return true;
            }

            Log::warning('Payment transaction not found for order approved webhook', [
                'order_id' => $orderId
            ]);

            return false;

        } catch (Exception $e) {
            Log::error('Error handling order approved: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Handle order completed webhook
     */
    private function handleOrderCompleted(array $resource): bool
    {
        try {
            $orderId = $resource['id'] ?? '';
            $status = $resource['status'] ?? '';

            // Find transaction by order ID
            $transaction = PaymentTransaction::where('order_id', $orderId)->first();

            if ($transaction) {
                $transaction->update([
                    'status' => 'completed',
                    'gateway_response' => json_encode($resource),
                    'updated_at' => now()
                ]);

                // Update shipment status and process shipment (create label + schedule pickup)
                if ($transaction->shipment) {
                    $shipment = $transaction->shipment;
                    $shipment->update([
                        'status' => 'paid',
                        'updated_at' => now()
                    ]);

                    Log::info('Webhook: Processing shipment after order completion', [
                        'shipment_id' => $shipment->id,
                        'transaction_id' => $transaction->id
                    ]);

                    // Trigger shipment processing (label + pickup)
                    try {
                        $fedexService = new \App\Services\FedExServiceFixed();
                        
                        // Create shipment with FedEx
                        $response = $fedexService->createShipment($shipment);

                        if ($response['success']) {
                            $shipment->tracking_number = $response['tracking_number'] ?? null;
                            $shipment->status = 'shipment_created';
                            $shipment->fedex_response = json_encode($response);
                            
                            // Update preferred_ship_date if it was adjusted
                            if (!empty($response['actual_ship_date'])) {
                                $shipment->preferred_ship_date = $response['actual_ship_date'];
                            }
                            
                            $shipment->save();

                            // Create shipment tags (label)
                            $tagResult = $fedexService->createShipmentTags($shipment, $response);
                            if ($tagResult['success']) {
                                $labelBase64 = $tagResult['label_base64'] ?? null;
                                $currentFedexResponse = json_decode($shipment->fedex_response, true) ?: [];

                                if ($labelBase64) {
                                    $filePath = storage_path("app/public/labels/{$shipment->id}.pdf");
                                    file_put_contents($filePath, base64_decode($labelBase64));
                                    $currentFedexResponse['label_url'] = asset("storage/labels/{$shipment->id}.pdf");
                                }

                                $shipment->fedex_response = json_encode($currentFedexResponse);
                                $shipment->status = 'label_generated';
                                $shipment->save();

                                // Send admin notification
                                $notificationService = new \App\Services\NotificationService();
                                $publicLabelPath = public_path("storage/labels/{$shipment->id}.pdf");
                                if (file_exists($publicLabelPath)) {
                                    $notificationService->sendAdminNewOrderNotification($shipment, $publicLabelPath);
                                }
                            }

                            // Schedule pickup if needed
                            if ($shipment->pickup_type == 'PICKUP' && !$shipment->pickup_scheduled) {
                                $pickupResult = $fedexService->schedulePickup($shipment);

                                if ($pickupResult['success']) {
                                    $shipment->pickup_scheduled = true;
                                    $shipment->pickup_confirmation = $pickupResult['confirmation_number'] ?? null;
                                    $shipment->pickup_date = $pickupResult['actual_pickup_date'] ?? $pickupResult['scheduled_date'] ?? null;
                                    
                                    // Update preferred_ship_date if it was adjusted
                                    if (!empty($pickupResult['actual_ship_date'])) {
                                        $shipment->preferred_ship_date = $pickupResult['actual_ship_date'];
                                    }
                                    
                                    $shipment->status = 'pickup_scheduled';
                                    $shipment->save();

                                    Log::info('Webhook: Pickup scheduled after order completion', [
                                        'shipment_id' => $shipment->id,
                                        'confirmation_number' => $pickupResult['confirmation_number'],
                                        'actual_pickup_date' => $pickupResult['actual_pickup_date'] ?? null,
                                        'actual_ship_date' => $pickupResult['actual_ship_date'] ?? null
                                    ]);

                                    // Send pickup confirmation email
                                    $notificationService = new \App\Services\NotificationService();
                                    $notificationService->sendPickupScheduled($shipment);
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Webhook: Error processing shipment after order completion', [
                            'shipment_id' => $shipment->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                Log::info('Order completed processed', [
                    'transaction_id' => $transaction->id,
                    'order_id' => $orderId
                ]);

                return true;
            }

            Log::warning('Payment transaction not found for order completed webhook', [
                'order_id' => $orderId
            ]);

            return false;

        } catch (Exception $e) {
            Log::error('Error handling order completed: ' . $e->getMessage());
            return false;
        }
    }
}
