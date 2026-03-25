<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Shipment;
use App\Models\ShipmentRate;
use Exception;
use Carbon\Carbon;

class FedExServiceFixed
{
    private $baseUrl;
    private $apiKey;
    private $secretKey;
    private $accountNumber;
    private $meterNumber;
    private $accessToken;

    public function __construct()
    {
        $this->baseUrl = config('services.fedex.base_url');
        $this->apiKey = config('services.fedex.api_key');
        $this->secretKey = config('services.fedex.secret_key');
        $this->accountNumber = config('services.fedex.account_number');
        $this->meterNumber = config('services.fedex.meter_number');
    }

    /**
     * Get OAuth token for FedEx API
     */
    private function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        try {
            $response = Http::asForm()->post($this->baseUrl . '/oauth/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $this->apiKey,
                'client_secret' => $this->secretKey
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $this->accessToken = $data['access_token'];
                return $this->accessToken;
            }

            throw new Exception('Failed to get FedEx access token: ' . $response->body());
        } catch (Exception $e) {
            Log::error('FedEx OAuth Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create shipment with FedEx
     */
    public function createShipment(Shipment $shipment): array
    {
        try {
            $token = $this->getAccessToken();

            // Determine if this is a domestic shipment
            $isDomestic = $this->isDomesticShipment($shipment);

        // Use selected rate's service type (required)
        $selectedRate = $shipment->selectedRate;
        if (!$selectedRate || empty($selectedRate->service_type)) {
            throw new \Exception('No service type selected. Please select a shipping rate before creating shipment.');
        }

        $serviceType = $selectedRate->service_type;
        Log::info('Using selected rate service type', [
            'shipment_id' => $shipment->id,
            'selected_service_type' => $serviceType,
            'rate_id' => $selectedRate->id
        ]);

            // Validate service type compatibility
            $this->validateServiceTypeCompatibility($serviceType, $shipment);

            // Check if this is a same-state shipment
            $isSameState = $shipment->sender_state === $shipment->recipient_state;

            // Get package weight for logging
            $packageWeight = $shipment->getTotalWeight();

            Log::info('Creating FedEx shipment', [
                'shipment_id' => $shipment->id,
                'package_weight' => $packageWeight,
                'bag_type' => $shipment->bag_type,
                'number_of_bags' => $shipment->number_of_bags,
                'is_domestic' => $isDomestic,
                'is_same_state' => $isSameState,
                'service_type' => $serviceType,
                'using_selected_rate' => !empty($selectedRate),
                'selected_rate_id' => $selectedRate->id ?? null,
                'sender_state' => $shipment->sender_state,
                'recipient_state' => $shipment->recipient_state
            ]);

            // Validate required fields
            $this->validateShipmentData($shipment);

            // Ensure shipment data is properly formatted for FedEx
            $this->ensureShipmentDataFormat($shipment);

            // Process addresses with proper validation
            $senderAddress = $this->formatAddress($shipment->sender_address_line);
            $recipientAddress = $this->formatAddress($shipment->recipient_address);

            // Format package description
            $packageDesc = $this->formatPackageDescription($shipment->package_description);

            // Use bag specifications if available, otherwise use manual dimensions
            $packageDimensions = $shipment->getTotalDimensions();

            // Validate and format dimensions
            $dimensions = $this->validatePackageDimensions(
                (float)$packageDimensions['length'],
                (float)$packageDimensions['width'],
                (float)$packageDimensions['height'],
                (float)$packageWeight
            );

            // Determine pickup type - use correct FedEx API values
            $pickupType = match($shipment->pickup_type ?? 'DROPOFF') {
                'PICKUP' => 'USE_SCHEDULED_PICKUP',
                'DROPOFF' => 'DROPOFF_AT_FEDEX_LOCATION',
                default => 'DROPOFF_AT_FEDEX_LOCATION'
            };

            // Log pickup type for debugging
            if ($pickupType === 'USE_SCHEDULED_PICKUP') {
                Log::info('Pickup requested - will be scheduled after shipment creation', [
                    'shipment_id' => $shipment->id,
                    'pickup_type' => $pickupType
                ]);
            }

            // Build payload according to FedEx Ship API documentation
            $payload = [
                'shipAction' => 'CONFIRM',
                'mergeLabelDocOption' => 'LABELS_AND_DOCS',
                'processingOptionType' => 'ALLOW_ASYNCHRONOUS',
                'oneLabelAtATime' => true,
                'labelResponseOptions' => 'LABEL',
                'accountNumber' => [
                    'value' => $this->accountNumber
                ],
                'requestedShipment' => [
                    'shipper' => [
                        'contact' => [
                            'personName' => $shipment->sender_full_name,
                            'emailAddress' => $shipment->sender_email,
                            'phoneNumber' => $shipment->sender_phone,
                            'companyName' => null, // Optional, can be null
                            'phoneExtension' => null, // Optional, can be null
                            'faxNumber' => null // Optional, can be null
                        ],
                        'address' => [
                            'streetLines' => [$senderAddress],
                            'city' => $shipment->sender_city,
                            'stateOrProvinceCode' => $shipment->sender_state,
                            'postalCode' => $shipment->sender_zipcode,
                            'countryCode' => 'US',
                            'residential' => false // Default to business for better service compatibility
                        ]
                    ],
                    'recipients' => [
                        [
                            'contact' => [
                                'personName' => $shipment->recipient_name,
                                'phoneNumber' => $shipment->recipient_phone,
                                'emailAddress' => $shipment->sender_email, // Use sender email as fallback for notifications
                                'companyName' => null, // Optional, can be null
                                'phoneExtension' => null, // Optional, can be null
                                'faxNumber' => null // Optional, can be null
                            ],
                            'address' => [
                                'streetLines' => [$recipientAddress],
                                'city' => $shipment->recipient_city,
                                'stateOrProvinceCode' => $shipment->recipient_state,
                                'postalCode' => $shipment->recipient_postal_code,
                                'countryCode' => 'US',
                                'residential' => false // Default to business for better service compatibility
                            ],
                            'deliveryInstructions' => null // Optional, can be null
                        ]
                    ],
                    'shipDatestamp' => $shipment->preferred_ship_date->format('Y-m-d'),
                    'serviceType' => $serviceType,
                    'packagingType' => $shipment->packaging_type ?? 'YOUR_PACKAGING',
                    'pickupType' => $pickupType,
                    'shippingChargesPayment' => [
                        'paymentType' => 'SENDER',
                        'payor' => [
                            'responsibleParty' => [
                                'accountNumber' => [
                                    'value' => $this->accountNumber
                                ]
                            ]
                        ]
                    ],
                    'labelSpecification' => [
                        'labelStockType' => 'PAPER_85X11_TOP_HALF_LABEL',
                        'imageType' => 'PDF'
                    ],
                    'requestedPackageLineItems' => [
                        [
                            'sequenceNumber' => 1,
                            'weight' => [
                                'units' => $shipment->weight_unit,
                                'value' => $dimensions['weight']
                            ],
                            'dimensions' => [
                                'length' => $dimensions['length'],
                                'width' => $dimensions['width'],
                                'height' => $dimensions['height'],
                                'units' => $shipment->dimension_unit
                            ],
                            'customerReferences' => [
                                [
                                    'customerReferenceType' => 'CUSTOMER_REFERENCE',
                                    'value' => 'Shipment ID: ' . $shipment->id
                                ]
                            ],
                            'declaredValue' => [
                                'amount' => floatval($shipment->declared_value ?? 100.00),
                                'currency' => $shipment->currency_code ?? 'USD'
                            ],
                            'itemDescription' => $packageDesc
                        ]
                    ],
                    'totalPackageCount' => 1,
                    'totalWeight' => $dimensions['weight'],
                    'totalDeclaredValue' => [
                        'amount' => floatval($shipment->declared_value ?? 100.00),
                        'currency' => $shipment->currency_code ?? 'USD'
                    ],
                    'preferredCurrency' => $shipment->currency_code ?? 'USD',
                    'rateRequestType' => ['LIST', 'PREFERRED']
                ]
            ];

            // Log the payload for debugging
            Log::debug('FedEx shipment payload', [
                'shipment_id' => $shipment->id,
                'pickup_type' => $pickupType,
                'service_type' => $shipment->selectedRate->service_type
            ]);

            // Also log the actual payload structure for debugging
            Log::debug('Full FedEx payload structure', [
                'shipment_id' => $shipment->id,
                'payload' => $payload
            ]);

            // Validate payload before sending
            $this->validateFedExPayload($payload);

            // Use the correct FedEx API endpoint
            $endpoint = $this->baseUrl . '/ship/v1/shipments';

            Log::info('Making FedEx API request', [
                'shipment_id' => $shipment->id,
                'endpoint' => $endpoint,
                'payload_size' => strlen(json_encode($payload))
            ]);

            // Log the full payload for debugging
            Log::info('FedEx API request payload', [
                'shipment_id' => $shipment->id,
                'payload_json' => json_encode($payload)
            ]);

            // Remove any null or empty fields recursively
            function array_filter_recursive($input)
            {
                foreach ($input as &$value) {
                    if (is_array($value)) {
                        $value = array_filter_recursive($value);
                    }
                }
                return array_filter($input, function ($v) {
                    return !is_null($v) && $v !== '';
                });
            }
            $payload = array_filter_recursive($payload);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'X-locale' => 'en_US'
            ])->post($endpoint, $payload);

            if ($response->successful()) {
                $data = $response->json();
                log::info("fedex response", $data);
                $trackingNumber = $data['output']['transactionShipments'][0]['pieceResponses'][0]['trackingNumber'] ?? null;
                $pieceResponse = $data['output']['transactionShipments'][0]['pieceResponses'][0] ?? null;

                $packageDocs = $pieceResponse['packageDocuments'][0] ?? null;
                $labelUrl = $packageDocs['url'] ?? null;
                $documentId = $packageDocs['documentId'] ?? null;
                $documentType = $packageDocs['contentType'] ?? null; // e.g. "LABEL"
                $documentEncoding = $packageDocs['encodedLabel'] ?? null; // base64 encoded PDF

                $masterTrackingNumber = $data['output']['transactionShipments'][0]['masterTrackingNumber'] ?? $trackingNumber;

                // Log successful response
                Log::info('FedEx shipment created successfully', [
                    'shipment_id' => $shipment->id,
                    'tracking_number' => $trackingNumber,
                    'master_tracking_number' => $masterTrackingNumber,
                    'has_label_url' => !empty($labelUrl),
                    'has_document_id' => !empty($documentId),
                    'document_type' => $documentType,
                    'document_encoding' => !empty($documentEncoding) ? 'base64' : 'none'
                ]);

                // Send email notifications after successful shipment creation
                try {
                    $notificationService = new \App\Services\NotificationService();

                    // Send shipping confirmation email to customer
                    $confirmationSent = $notificationService->sendShippingConfirmation($shipment);
                    Log::info('Shipping confirmation email sent', [
                        'shipment_id' => $shipment->id,
                        'success' => $confirmationSent
                    ]);

                    // Determine the local file path for the label
                    $labelFilePath = null;

                    if (!empty($documentEncoding)) {
                        // Save base64 label directly to public directory for web access
                        $publicLabelsDir = public_path("storage/labels");
                        if (!file_exists($publicLabelsDir)) {
                            mkdir($publicLabelsDir, 0755, true);
                        }
                        $labelFilePath = $publicLabelsDir . "/{$shipment->id}.pdf";
                        file_put_contents($labelFilePath, base64_decode($documentEncoding));

                        // Also save to storage for backup
                        $storageLabelsDir = storage_path("app/public/labels");
                        if (!file_exists($storageLabelsDir)) {
                            mkdir($storageLabelsDir, 0755, true);
                        }
                        $storageLabelPath = $storageLabelsDir . "/{$shipment->id}.pdf";
                        copy($labelFilePath, $storageLabelPath);
                    } elseif (!empty($labelUrl) && str_contains($labelUrl, asset('storage/labels/'))) {
                        // Convert asset URL to local file path
                        $fileName = basename($labelUrl);
                        $labelFilePath = storage_path("app/public/labels/{$fileName}");
                    }

                    // Send shipping label email to customer if label is available
                    if (!empty($labelUrl) || !empty($documentEncoding)) {
                        $labelSent = $notificationService->sendShippingLabel($shipment, $labelFilePath);
                        Log::info('Shipping label email sent to customer', [
                            'shipment_id' => $shipment->id,
                            'success' => $labelSent,
                            'has_label_file' => !empty($labelFilePath),
                            'label_file_path' => $labelFilePath
                        ]);
                    }

                } catch (\Exception $emailException) {
                    // Log email errors but don't fail the shipment creation
                    Log::error('Email notification failed after shipment creation', [
                        'shipment_id' => $shipment->id,
                        'error' => $emailException->getMessage(),
                        'tracking_number' => $trackingNumber
                    ]);
                }

                return [
                    'success' => true,
                    'tracking_number' => $trackingNumber,
                    'master_tracking_number' => $masterTrackingNumber,
                    'label_url' => $labelUrl,              // direct link (if available)
                    'document_id' => $documentId,          // use with Tags API to fetch later
                    'document_type' => $documentType,      // useful to confirm PDF type
                    'base64_pdf' => $documentEncoding,     // if FedEx returns encoded PDF
                    'message' => 'Shipment created successfully',
                    'response_data' => $data
                ];
            }


            $errorBody = $response->body();
            Log::error('FedEx Shipment API Error', [
                'shipment_id' => $shipment->id,
                'status_code' => $response->status(),
                'response_body' => $errorBody
            ]);

            // Try to parse error response
            $errorMessage = 'Failed to create FedEx shipment';
            try {
                $errorData = $response->json();
                if (isset($errorData['errors']) && is_array($errorData['errors']) && !empty($errorData['errors'])) {
                    $errorMessage .= ': ' . ($errorData['errors'][0]['message'] ?? 'Unknown error');

                    // Log more detailed error information
                    Log::error('FedEx API Error Details', [
                        'shipment_id' => $shipment->id,
                        'errors' => $errorData['errors'],
                        'transaction_id' => $errorData['transactionId'] ?? 'Unknown'
                    ]);

                    // Check for service type errors that need fallback
                    $needsFallback = false;
                    $errorType = '';

                    foreach ($errorData['errors'] as $error) {
                        $errorCode = $error['code'] ?? '';

                        if (strpos($errorCode, 'SERVICETYPEANDADDRESS.MISMATCH') !== false) {
                            $needsFallback = true;
                            $errorType = 'SERVICETYPEANDADDRESS.MISMATCH';
                            Log::info('Found service/address mismatch error', [
                                'shipment_id' => $shipment->id,
                                'error_code' => $errorCode,
                                'error_message' => $error['message'] ?? 'No message'
                            ]);
                            break;
                        } elseif (strpos($errorCode, 'SERVICETYPE.NOTSUPPORTED') !== false) {
                            $needsFallback = true;
                            $errorType = 'SERVICETYPE.NOTSUPPORTED';
                            Log::info('Found service type not supported error', [
                                'shipment_id' => $shipment->id,
                                'error_code' => $errorCode,
                                'error_message' => $error['message'] ?? 'No message'
                            ]);
                            break;
                        }
                    }

                    if ($needsFallback) {
                        // Always fail gracefully - no automatic fallbacks
                        Log::error('Service type not available - failing gracefully', [
                            'shipment_id' => $shipment->id,
                            'service_type' => $serviceType,
                            'error_code' => $errorType,
                            'message' => 'The selected service type is not available for this route.'
                        ]);

                        // Provide clear error message to user
                        $errorMessage = $this->getServiceErrorMessage($serviceType, $errorType);
                        throw new \Exception($errorMessage);
                    } else {
                        Log::info('No service type errors detected', [
                            'shipment_id' => $shipment->id,
                            'error_codes' => array_column($errorData['errors'], 'code')
                        ]);
                    }
                }
            } catch (\Exception $parseException) {
                // Parsing failed, use generic message
                Log::error('Failed to parse FedEx error response', [
                    'shipment_id' => $shipment->id,
                    'exception' => $parseException->getMessage(),
                    'response_body' => $errorBody
                ]);
            }

            throw new Exception($errorMessage);
        } catch (Exception $e) {
            Log::error('FedEx Shipment Error', [
                'shipment_id' => $shipment->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'SHIPMENT_CREATION_ERROR',
            ];
        }
    }

    /**
     * Validate shipment data for FedEx API
     */
    private function validateShipmentData(Shipment $shipment): void
    {
        $errors = [];

        // Check required fields
        if (empty($shipment->sender_full_name)) {
            $errors[] = 'Sender name is required';
        }

        if (empty($shipment->sender_phone)) {
            $errors[] = 'Sender phone is required';
        }

        if (empty($shipment->sender_email)) {
            $errors[] = 'Sender email is required';
        }

        if (empty($shipment->sender_address_line)) {
            $errors[] = 'Sender address is required';
        }

        if (empty($shipment->sender_city)) {
            $errors[] = 'Sender city is required';
        }

        if (empty($shipment->sender_state)) {
            $errors[] = 'Sender state is required';
        }

        if (empty($shipment->sender_zipcode)) {
            $errors[] = 'Sender zipcode is required';
        }

        if (empty($shipment->recipient_name)) {
            $errors[] = 'Recipient name is required';
        }

        if (empty($shipment->recipient_phone)) {
            $errors[] = 'Recipient phone is required';
        }

        // Enhanced address validation
        if (empty($shipment->recipient_address)) {
            $errors[] = 'Recipient address is required';
        }

        if (empty($shipment->recipient_city)) {
            $errors[] = 'Recipient city is required';
        }

        if (empty($shipment->recipient_state)) {
            $errors[] = 'Recipient state is required';
        }

        if (empty($shipment->recipient_postal_code)) {
            $errors[] = 'Recipient postal code is required';
        }

        // Validate package dimensions
        if (empty($shipment->package_length) || $shipment->package_length <= 0) {
            $errors[] = 'Package length must be greater than 0';
        }

        if (empty($shipment->package_width) || $shipment->package_width <= 0) {
            $errors[] = 'Package width must be greater than 0';
        }

        if (empty($shipment->package_height) || $shipment->package_height <= 0) {
            $errors[] = 'Package height must be greater than 0';
        }

        if (empty($shipment->package_weight) || $shipment->package_weight <= 0) {
            $errors[] = 'Package weight must be greater than 0';
        }

        // Validate postal codes (basic US format)
        if (!preg_match('/^\d{5}(-\d{4})?$/', $shipment->sender_zipcode)) {
            $errors[] = 'Invalid sender zipcode format';
        }

        if (!preg_match('/^\d{5}(-\d{4})?$/', $shipment->recipient_postal_code)) {
            $errors[] = 'Invalid recipient postal code format';
        }

        // Validate email format
        if (!filter_var($shipment->sender_email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid sender email format';
        }

        if (!empty($errors)) {
            throw new Exception('Shipment validation failed: ' . implode(', ', $errors));
        }
    }

    /**
     * Ensure shipment data is properly formatted for FedEx API
     * This method ensures all data meets FedEx requirements
     */
    private function ensureShipmentDataFormat(Shipment $shipment): void
    {
        // Debug logging
        Log::info('ensureShipmentDataFormat called', [
            'shipment_id' => $shipment->id,
            'original_sender_phone' => $shipment->sender_phone,
            'original_recipient_phone' => $shipment->recipient_phone
        ]);

        // Ensure names are properly formatted
        $shipment->sender_full_name = trim($shipment->sender_full_name);
        $shipment->recipient_name = trim($shipment->recipient_name);

        // Ensure emails are lowercase
        $shipment->sender_email = strtolower(trim($shipment->sender_email));

        // Ensure phone numbers are properly formatted
        $shipment->sender_phone = $this->formatPhoneNumber($shipment->sender_phone);
        $shipment->recipient_phone = $this->formatPhoneNumber($shipment->recipient_phone);

        // Debug logging after formatting
        Log::info('Phone numbers after formatting', [
            'shipment_id' => $shipment->id,
            'formatted_sender_phone' => $shipment->sender_phone,
            'formatted_recipient_phone' => $shipment->recipient_phone
        ]);

        // Ensure addresses are properly formatted
        $shipment->sender_address_line = $this->formatAddress($shipment->sender_address_line);
        $shipment->recipient_address = $this->formatAddress($shipment->recipient_address);

        // Ensure cities are properly formatted
        $shipment->sender_city = trim($shipment->sender_city);
        $shipment->recipient_city = trim($shipment->recipient_city);

        // Ensure states are uppercase
        $shipment->sender_state = strtoupper(trim($shipment->sender_state));
        $shipment->recipient_state = strtoupper(trim($shipment->recipient_state));

        // Ensure postal codes are properly formatted
        $shipment->sender_zipcode = trim($shipment->sender_zipcode);
        $shipment->recipient_postal_code = trim($shipment->recipient_postal_code);

        // Ensure package description is properly formatted
        $shipment->package_description = $this->formatPackageDescription($shipment->package_description);

        // Ensure weight and dimension units are properly set
        $shipment->weight_unit = $shipment->weight_unit ?: 'LB';
        $shipment->dimension_unit = $shipment->dimension_unit ?: 'IN';

        // Ensure currency code is set
        $shipment->currency_code = $shipment->currency_code ?: 'USD';

        // Ensure declared value is set
        if (empty($shipment->declared_value) || $shipment->declared_value <= 0) {
            $shipment->declared_value = 100.00;
        }
    }

    /**
     * Validate FedEx payload before sending to API
     * This helps catch formatting issues early
     */
    private function validateFedExPayload(array $payload): void
    {
        $errors = [];

        // Validate account number
        if (empty($payload['accountNumber']['value'])) {
            $errors[] = 'Account number is required';
        }

        // Validate shipper information
        if (empty($payload['requestedShipment']['shipper']['contact']['personName'])) {
            $errors[] = 'Shipper name is required';
        }

        if (empty($payload['requestedShipment']['shipper']['contact']['emailAddress'])) {
            $errors[] = 'Shipper email is required';
        }

        if (empty($payload['requestedShipment']['shipper']['contact']['phoneNumber'])) {
            $errors[] = 'Shipper phone is required';
        }

        // Validate shipper address
        if (empty($payload['requestedShipment']['shipper']['address']['streetLines'][0])) {
            $errors[] = 'Shipper address is required';
        }

        if (empty($payload['requestedShipment']['shipper']['address']['city'])) {
            $errors[] = 'Shipper city is required';
        }

        if (empty($payload['requestedShipment']['shipper']['address']['stateOrProvinceCode'])) {
            $errors[] = 'Shipper state is required';
        }

        if (empty($payload['requestedShipment']['shipper']['address']['postalCode'])) {
            $errors[] = 'Shipper postal code is required';
        }

        // Validate recipient information
        if (empty($payload['requestedShipment']['recipients'][0]['contact']['personName'])) {
            $errors[] = 'Recipient name is required';
        }

        if (empty($payload['requestedShipment']['recipients'][0]['contact']['phoneNumber'])) {
            $errors[] = 'Recipient phone is required';
        }

        // Validate recipient address
        if (empty($payload['requestedShipment']['recipients'][0]['address']['streetLines'][0])) {
            $errors[] = 'Recipient address is required';
        }

        if (empty($payload['requestedShipment']['recipients'][0]['address']['city'])) {
            $errors[] = 'Recipient city is required';
        }

        if (empty($payload['requestedShipment']['recipients'][0]['address']['stateOrProvinceCode'])) {
            $errors[] = 'Recipient state is required';
        }

        if (empty($payload['requestedShipment']['recipients'][0]['address']['postalCode'])) {
            $errors[] = 'Recipient postal code is required';
        }

        // Validate package information
        if (empty($payload['requestedShipment']['requestedPackageLineItems'][0]['weight']['value'])) {
            $errors[] = 'Package weight is required';
        }

        if (empty($payload['requestedShipment']['requestedPackageLineItems'][0]['dimensions']['length'])) {
            $errors[] = 'Package length is required';
        }

        if (empty($payload['requestedShipment']['requestedPackageLineItems'][0]['dimensions']['width'])) {
            $errors[] = 'Package width is required';
        }

        if (empty($payload['requestedShipment']['requestedPackageLineItems'][0]['dimensions']['height'])) {
            $errors[] = 'Package height is required';
        }

        // Validate phone number format (should be 10 digits)
        $shipperPhone = $payload['requestedShipment']['shipper']['contact']['phoneNumber'];
        $recipientPhone = $payload['requestedShipment']['recipients'][0]['contact']['phoneNumber'];

        if (strlen($shipperPhone) !== 10) {
            $errors[] = 'Shipper phone must be exactly 10 digits';
        }

        if (strlen($recipientPhone) !== 10) {
            $errors[] = 'Recipient phone must be exactly 10 digits';
        }

        if (!empty($errors)) {
            throw new Exception('FedEx payload validation failed: ' . implode(', ', $errors));
        }
    }

    /**
     * Test FedEx API connection
     */
    public function testFedExConnection(): array
    {
        try {
            $token = $this->getAccessToken();

            // Simple authentication test - just verify we can get a token
            // No need for dummy data, just test the authentication endpoint

            // Just test authentication - if we get here, the token is valid
            return [
                'success' => true,
                'message' => 'FedEx API authentication successful',
                'token_obtained' => !empty($token)
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'FedEx API connection error: ' . $e->getMessage(),
                'exception' => $e->getMessage()
            ];
        }
    }

    /**
     * Format address for FedEx API
     * Ensure address is properly formatted and not empty
     */
    private function formatAddress(string $address): string
    {
        $formatted = trim($address);

        // Ensure address is not empty
        if (empty($formatted)) {
            throw new \Exception('Address cannot be empty');
        }

        // Remove extra spaces and normalize
        $formatted = preg_replace('/\s+/', ' ', $formatted);

        // Ensure it's not too long (FedEx has limits)
        if (strlen($formatted) > 35) {
            $formatted = substr($formatted, 0, 35);
        }

        return $formatted;
    }

    /**
     * Format package description for FedEx API
     * Ensure description is properly formatted and not empty
     */
    private function formatPackageDescription(string $description): string
    {
        $formatted = trim($description);

        // Ensure description is not empty
        if (empty($formatted)) {
            throw new \Exception('Package description cannot be empty');
        }

        // Remove extra spaces and normalize
        $formatted = preg_replace('/\s+/', ' ', $formatted);

        // Ensure it's not too long (FedEx has limits)
        if (strlen($formatted) > 50) {
            $formatted = substr($formatted, 0, 50);
        }

        return $formatted;
    }

    /**
     * Format phone number for FedEx API
     * FedEx requires exactly 10 digits for US phone numbers
     */
    public function formatPhoneNumber(string $phone): string
    {
        // Strip all non-numeric characters
        $cleaned = preg_replace('/[^\d]/', '', $phone);

        // Handle the specific case from the log where we have 11 digits starting with 1
        // "11216896914" should become "1216896914" (10 digits)
        // "11178243531" should become "1178243531" (10 digits)
        if (strlen($cleaned) === 11 && substr($cleaned, 0, 1) === '1') {
            $cleaned = substr($cleaned, 1);
        }

        // Ensure it's exactly 10 digits for US numbers
        if (strlen($cleaned) === 10) {
            return $cleaned;
        }

        // If not 10 digits, pad with zeros
        if (strlen($cleaned) < 10) {
            return str_pad($cleaned, 10, '0', STR_PAD_RIGHT);
        }

        // If more than 10 digits, take the last 10
        if (strlen($cleaned) > 10) {
            $cleaned = substr($cleaned, -10);
        }

        // Final validation - ensure it's exactly 10 digits
        if (strlen($cleaned) !== 10) {
            // If still not 10 digits, throw an exception
            throw new \Exception('Invalid phone number format. Expected 10 digits, got ' . strlen($cleaned));
        }

        return $cleaned;
    }

    /**
     * Validate and format package dimensions for FedEx API
     * FedEx requires integer values for dimensions and weight
     */
    private function validatePackageDimensions(float $length, float $width, float $height, float $weight): array
    {
        // Ensure minimum values and convert to integers
        $length = max(1, intval(round($length)));
        $width = max(1, intval(round($width)));
        $height = max(1, intval(round($height)));
        $weight = max(1, intval(round($weight)));

        // FedEx has maximum limits
        $length = min($length, 108); // 108 inches max
        $width = min($width, 108);
        $height = min($height, 108);
        $weight = min($weight, 150); // 150 lbs max

        return [
            'length' => $length,
            'width' => $width,
            'height' => $height,
            'weight' => $weight
        ];
    }

    /**
     * Map delivery type to FedEx service type
     */
    /**
     * Determine if shipment is domestic (US to US)
     */
    private function isDomesticShipment(Shipment $shipment): bool
    {
        // For now, assume all shipments are domestic US to US
        // This system appears to be designed for US domestic shipping only
        // based on the form fields and state/city selection

        // Validate that we have US state codes
        $senderState = $shipment->sender_state ?? '';
        $recipientState = $shipment->recipient_state ?? '';

        // Check if states are valid US state codes (2 letters)
        $isValidUSState = function($state) {
            return strlen($state) === 2 && ctype_alpha($state);
        };

        return $isValidUSState($senderState) && $isValidUSState($recipientState);
    }

    /**
     * Validate service type compatibility with shipment details
     */
    private function validateServiceTypeCompatibility(string $serviceType, Shipment $shipment): void
    {
        $errors = [];

        // Weight limits removed - no longer enforcing 150 lbs limit

        // Check for Alaska/Hawaii restrictions
        $recipientState = $shipment->recipient_state ?? '';
        if (in_array($recipientState, ['AK', 'HI']) && str_contains($serviceType, 'GROUND')) {
            // FedEx Ground has limited service to Alaska and Hawaii
            Log::warning('FedEx Ground service to Alaska/Hawaii may have limited availability', [
                'shipment_id' => $shipment->id,
                'recipient_state' => $recipientState,
                'service_type' => $serviceType
            ]);
        }

        if (!empty($errors)) {
            throw new \Exception('Service type validation failed: ' . implode(', ', $errors));
        }
    }





    /**
     * Get user-friendly error message for service type failures
     */
    private function getServiceErrorMessage(string $serviceType, string $errorType): string
    {
        switch ($errorType) {
            case 'SERVICETYPEANDADDRESS.MISMATCH':
                return "The selected service type '{$serviceType}' is not available for this route. Please try a different service type or contact support.";

            case 'SERVICETYPE.NOTSUPPORTED':
                if ($serviceType === 'FEDEX_HOME_DELIVERY') {
                    return "FedEx Home Delivery is not available for this route. Please select a different service type.";
                }
                return "The selected service type '{$serviceType}' is not supported for this route. Please try a different service type.";

            default:
                return "The selected service type '{$serviceType}' is not available for this route. Please try a different service type.";
        }
    }


    /**
     * Schedule a pickup with FedEx
     */
    public function schedulePickup(Shipment $shipment): array
    {
        Log::info('=== FEDEX PICKUP SCHEDULING METHOD CALLED ===', [
            'shipment_id' => $shipment->id,
            'method' => 'schedulePickup',
            'timestamp' => now()->format('Y-m-d H:i:s')
        ]);

        try {
            Log::info('=== FEDEX PICKUP SCHEDULING STARTED ===', [
                'shipment_id' => $shipment->id,
                'pickup_type' => $shipment->pickup_type,
                'pickup_address' => $shipment->sender_address_line,
                'pickup_date' => $shipment->preferred_ship_date->format('Y-m-d'),
                'service_type' => $shipment->selectedRate->service_type
            ]);

            $token = $this->getAccessToken();

            Log::info('FedEx access token obtained for pickup scheduling', [
                'shipment_id' => $shipment->id,
                'token_obtained' => !empty($token)
            ]);

            // Validate and limit package weight
            $weight = (float)min($shipment->package_weight, 150);
            $formattedWeight = number_format($weight, 2);

            // Validate required address data
            if (empty($shipment->sender_address_line) || empty($shipment->sender_city) ||
                empty($shipment->sender_state) || empty($shipment->sender_zipcode)) {
                throw new \Exception('Missing required sender address information for pickup scheduling');
            }

            $pickupAddress = $shipment->sender_address_line;
            $pickupCity = $shipment->sender_city;
            $pickupState = $shipment->sender_state;
            $pickupPostalCode = $shipment->sender_zipcode;

            // Format date for pickup - ensure it's a business day and handle cutoff times
            $pickupDate = \Carbon\Carbon::parse($shipment->preferred_ship_date);
            $now = \Carbon\Carbon::now();
            
            // FedEx cutoff time is typically 3 PM (15:00) for same-day pickup
            $cutoffHour = 15; // 3 PM
            
            // If date is in the past or is a weekend, move to next business day
            if ($pickupDate->isPast() || $pickupDate->isWeekend()) {
                $pickupDate = \Carbon\Carbon::now()->addDay();
                while ($pickupDate->isWeekend()) {
                    $pickupDate = $pickupDate->addDay();
                }
            }
            
            // If pickup date is today and current time is past cutoff, move to next business day
            if ($pickupDate->isToday() && $now->hour >= $cutoffHour) {
                Log::info('Current time past cutoff, moving to next business day', [
                    'shipment_id' => $shipment->id,
                    'current_hour' => $now->hour,
                    'cutoff_hour' => $cutoffHour
                ]);
                
                $pickupDate = \Carbon\Carbon::now()->addDay();
                while ($pickupDate->isWeekend()) {
                    $pickupDate = $pickupDate->addDay();
                }
            }

            Log::info('Pickup date validation', [
                'shipment_id' => $shipment->id,
                'original_date' => $shipment->preferred_ship_date->format('Y-m-d'),
                'final_date' => $pickupDate->format('Y-m-d'),
                'is_weekend' => $pickupDate->isWeekend(),
                'day_of_week' => $pickupDate->format('l'),
                'current_hour' => $now->hour,
                'is_past_cutoff' => $now->hour >= $cutoffHour
            ]);

            // Format times according to FedEx documentation
            // Use safe times that are well before cutoff (8 AM - 2 PM window)
            $timeSlot = $shipment->pickup_time_slot ?? 'morning';
            
            // Always schedule pickup for the pickup date, not today
            $pickupDateStr = $pickupDate->format('Y-m-d');
            
            switch ($timeSlot) {
                case 'morning':
                    $readyTime = $pickupDateStr . 'T08:00:00-05:00';
                    $closeTime = '12:00:00';
                    break;
                case 'afternoon':
                    $readyTime = $pickupDateStr . 'T11:00:00-05:00';
                    $closeTime = '15:00:00';
                    break;
                case 'evening':
                    // Evening pickups are risky - use early afternoon instead
                    $readyTime = $pickupDateStr . 'T10:00:00-05:00';
                    $closeTime = '14:00:00';
                    break;
                default:
                    $readyTime = $pickupDateStr . 'T09:00:00-05:00';
                    $closeTime = '15:00:00';
            }

            // Determine carrier code based on service type
            $serviceType = $shipment->selectedRate->service_type;
            $carrierCode = str_starts_with($serviceType, 'FEDEX_GROUND') ? 'FDXG' : 'FDXE';

            // Build payload according to FedEx Pickup API documentation
            $payload = [
                'associatedAccountNumber' => [
                    'value' => $this->accountNumber
                ],
                'originDetail' => [
                    'pickupLocation' => [
                        'contact' => [
                            'personName' => $shipment->sender_full_name,
                            'phoneNumber' => $this->formatPhoneNumber($shipment->sender_phone)
                        ],
                        'address' => [
                            'streetLines' => [$pickupAddress],
                            'city' => $pickupCity,
                            'stateOrProvinceCode' => $pickupState,
                            'postalCode' => $pickupPostalCode,
                            'countryCode' => 'US'
                        ]
                    ],
                    'packageLocation' => 'FRONT',
                    'readyDateTimestamp' => $readyTime,
                    'customerCloseTime' => $closeTime
                ],
                'carrierCode' => $carrierCode,
                'totalPackageCount' => 1,
                'totalWeight' => [
                    'units' => 'LB',
                    'value' => $formattedWeight
                ]
            ];

            // Log the complete payload for debugging
            Log::info('FedEx pickup payload FULL', [
                'shipment_id' => $shipment->id,
                'payload' => $payload
            ]);

            // Make the API request with retry logic for service unavailable errors
            $maxRetries = 3;
            $retryDelay = 5; // seconds
            $response = null;

            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                Log::info('Making FedEx pickup API request', [
                    'shipment_id' => $shipment->id,
                    'endpoint' => $this->baseUrl . '/pickup/v1/pickups',
                    'payload_size' => strlen(json_encode($payload)),
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries
                ]);

                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'X-locale' => 'en_US'
                ])->post($this->baseUrl . '/pickup/v1/pickups', $payload);

                // Check if it's a service unavailable error and we can retry
                if ($response->status() === 503) {
                    $errorData = $response->json();
                    if (isset($errorData['errors'][0]['code']) && $errorData['errors'][0]['code'] === 'SERVICE.UNAVAILABLE.ERROR') {
                        if ($attempt < $maxRetries) {
                            Log::warning('FedEx pickup service unavailable, retrying', [
                                'shipment_id' => $shipment->id,
                                'attempt' => $attempt,
                                'next_retry_in' => $retryDelay . ' seconds'
                            ]);
                            sleep($retryDelay);
                            continue;
                        }
                    }
                }

                // If we get here, either it's not a retryable error or we've exhausted retries
                break;
            }

            if ($response->successful()) {
                $data = $response->json();
                $confirmationNumber = $data['output']['pickupConfirmationCode'] ?? null;
                $location = $data['output']['location'] ?? null;
                $scheduledDate = $data['output']['scheduledDate'] ?? $pickupDate->format('Y-m-d');

                Log::info('=== FEDEX PICKUP API RESPONSE SUCCESS ===', [
                    'shipment_id' => $shipment->id,
                    'status_code' => $response->status(),
                    'confirmation_number' => $confirmationNumber,
                    'location' => $location,
                    'scheduled_date' => $scheduledDate,
                    'carrier_code' => $carrierCode,
                    'response_keys' => array_keys($data)
                ]);

                // Log successful response
                Log::info('FedEx pickup scheduled successfully', [
                    'shipment_id' => $shipment->id,
                    'confirmation_number' => $confirmationNumber,
                    'location' => $location,
                    'scheduled_date' => $scheduledDate,
                    'carrier_code' => $carrierCode
                ]);

                return [
                    'success' => true,
                    'confirmation_number' => $confirmationNumber,
                    'location' => $location,
                    'scheduled_date' => $scheduledDate,
                    'carrier_code' => $carrierCode,
                    'message' => 'Pickup scheduled successfully',
                    'response_data' => $data
                ];
            }

            $errorBody = $response->body();
            $errorData = $response->json();
            Log::error('=== FEDEX PICKUP API ERROR ===', [
                'shipment_id' => $shipment->id,
                'status_code' => $response->status(),
                'response_body' => $errorBody,
                'error_json' => $errorData,
                'carrier_code' => $carrierCode,
                'service_type' => $serviceType,
                'endpoint' => $this->baseUrl . '/pickup/v1/pickups'
            ]);

            // Try to parse error response
            $errorMessage = 'Failed to schedule FedEx pickup';
            try {
                $errorData = $response->json();
                if (isset($errorData['errors']) && is_array($errorData['errors']) && !empty($errorData['errors'])) {
                    $errorCode = $errorData['errors'][0]['code'] ?? '';
                    $errorMessage .= ': ' . ($errorData['errors'][0]['message'] ?? 'Unknown error');

                    // Handle specific error codes
                    if ($errorCode === 'SERVICE.UNAVAILABLE.ERROR') {
                        $errorMessage = 'FedEx pickup service is temporarily unavailable. The shipment has been created successfully, but pickup scheduling failed. You can manually schedule the pickup later or contact support.';
                        Log::warning('FedEx pickup service unavailable', [
                            'shipment_id' => $shipment->id,
                            'error_code' => $errorCode,
                            'message' => 'Service temporarily unavailable - shipment created but pickup failed'
                        ]);

                        // Don't throw exception for service unavailable - just log and continue
                        return [
                            'success' => false,
                            'error' => 'pickup_service_unavailable',
                            'message' => $errorMessage,
                            'shipment_created' => true,
                            'can_retry' => true
                        ];
                    } elseif ($errorCode === 'PICKUPDATE.NOT.WORKINGDAY') {
                        $errorMessage = 'The selected pickup date is not a working day. Please select a business day for pickup.';
                        Log::warning('Pickup date not a working day', [
                            'shipment_id' => $shipment->id,
                            'pickup_date' => $pickupDate->format('Y-m-d'),
                            'day_of_week' => $pickupDate->format('l')
                        ]);
                    }

                    // Log more detailed error information
                    Log::error('FedEx API Pickup Error Details', [
                        'shipment_id' => $shipment->id,
                        'errors' => $errorData['errors'],
                        'transaction_id' => $errorData['transactionId'] ?? 'Unknown',
                        'carrier_code' => $carrierCode
                    ]);
                }
            } catch (\Exception $parseException) {
                // Parsing failed, use generic message
                Log::error('Failed to parse FedEx pickup error response', [
                    'shipment_id' => $shipment->id,
                    'exception' => $parseException->getMessage(),
                    'response_body' => $errorBody
                ]);
            }

            throw new \Exception($errorMessage);
        } catch (\Exception $e) {
            Log::error('FedEx Pickup Error', [
                'shipment_id' => $shipment->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'PICKUP_SCHEDULING_ERROR'
            ];
        }
    }

    /**
     * Create shipment tags after successful shipment creation
     */
    public function createShipmentTags(Shipment $shipment, array $shipmentResponse): array
    {
        try {
            Log::info('Creating FedEx shipment tags', [
                'shipment_id' => $shipment->id,
                'tracking_number' => $shipmentResponse['tracking_number'] ?? null,
                'has_label_url' => !empty($shipmentResponse['label_url']),
                'has_base64_pdf' => !empty($shipmentResponse['base64_pdf']),
                'response_keys' => array_keys($shipmentResponse)
            ]);

            $labelUrl = $shipmentResponse['label_url'] ?? null;

            // Extract base64 PDF from the response data
            $labelBase64 = null;
            if (isset($shipmentResponse['response_data']['output']['transactionShipments'][0]['pieceResponses'][0]['packageDocuments'][0]['encodedLabel'])) {
                $labelBase64 = $shipmentResponse['response_data']['output']['transactionShipments'][0]['pieceResponses'][0]['packageDocuments'][0]['encodedLabel'];
                Log::info('Found base64 PDF in response_data path', [
                    'shipment_id' => $shipment->id,
                    'base64_length' => strlen($labelBase64)
                ]);
            } elseif (isset($shipmentResponse['base64_pdf'])) {
                $labelBase64 = $shipmentResponse['base64_pdf'];
                Log::info('Found base64 PDF in base64_pdf field', [
                    'shipment_id' => $shipment->id,
                    'base64_length' => strlen($labelBase64)
                ]);
            }

            if ($labelUrl) {
                // Case 1: FedEx gave us a direct URL
                Log::info('=== FEDEX TAG CREATION SUCCESS (using label URL) ===', [
                    'shipment_id' => $shipment->id,
                    'label_url' => $labelUrl
                ]);

                return [
                    'success' => true,
                    'tag_url' => $labelUrl,
                    'message' => 'Shipment label available from shipment creation (URL)',
                    'response_data' => ['label_url' => $labelUrl]
                ];
            } elseif ($labelBase64) {
                // Case 2: FedEx gave us raw Base64 label
                $labelsDir = storage_path("app/public/labels");
                if (!file_exists($labelsDir)) {
                    mkdir($labelsDir, 0755, true);
                }

                $filePath = $labelsDir . "/{$shipment->id}.pdf";
                file_put_contents($filePath, base64_decode($labelBase64));

                $publicUrl = asset("storage/labels/{$shipment->id}.pdf");

                Log::info('=== FEDEX TAG CREATION SUCCESS (using base64 label) ===', [
                    'shipment_id' => $shipment->id,
                    'local_label_url' => $publicUrl
                ]);

                return [
                    'success' => true,
                    'tag_url' => $publicUrl,
                    'label_base64' => $labelBase64, // Also return the base64 for the controller
                    'message' => 'Shipment label generated from base64',
                    'response_data' => ['local_file' => $filePath]
                ];
            }

            Log::warning('No label data found in shipment response', [
                'shipment_id' => $shipment->id,
                'shipment_response_keys' => array_keys($shipmentResponse)
            ]);

            return [
                'success' => false,
                'message' => 'No label data found in shipment response',
                'error_code' => 'TAG_CREATION_ERROR'
            ];
        } catch (\Exception $e) {
            Log::error('FedEx Tag Creation Error', [
                'shipment_id' => $shipment->id,
                'exception' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'TAG_CREATION_ERROR'
            ];
        }
    }



    /**
     * Get available states for FedEx shipping
     */
    public function getAvailableStates(): array
    {
        return [
            'AL' => 'Alabama',
            'AK' => 'Alaska',
            'AZ' => 'Arizona',
            'AR' => 'Arkansas',
            'CA' => 'California',
            'CO' => 'Colorado',
            'CT' => 'Connecticut',
            'DE' => 'Delaware',
            'DC' => 'District of Columbia',
            'FL' => 'Florida',
            'GA' => 'Georgia',
            'HI' => 'Hawaii',
            'ID' => 'Idaho',
            'IL' => 'Illinois',
            'IN' => 'Indiana',
            'IA' => 'Iowa',
            'KS' => 'Kansas',
            'KY' => 'Kentucky',
            'LA' => 'Louisiana',
            'ME' => 'Maine',
            'MD' => 'Maryland',
            'MA' => 'Massachusetts',
            'MI' => 'Michigan',
            'MN' => 'Minnesota',
            'MS' => 'Mississippi',
            'MO' => 'Missouri',
            'MT' => 'Montana',
            'NE' => 'Nebraska',
            'NV' => 'Nevada',
            'NH' => 'New Hampshire',
            'NJ' => 'New Jersey',
            'NM' => 'New Mexico',
            'NY' => 'New York',
            'NC' => 'North Carolina',
            'ND' => 'North Dakota',
            'OH' => 'Ohio',
            'OK' => 'Oklahoma',
            'OR' => 'Oregon',
            'PA' => 'Pennsylvania',
            'RI' => 'Rhode Island',
            'SC' => 'South Carolina',
            'SD' => 'South Dakota',
            'TN' => 'Tennessee',
            'TX' => 'Texas',
            'UT' => 'Utah',
            'VT' => 'Vermont',
            'VA' => 'Virginia',
            'WA' => 'Washington',
            'WV' => 'West Virginia',
            'WI' => 'Wisconsin',
            'WY' => 'Wyoming',
        ];
    }

    /**
     * Get shipping rates for a shipment
     */
    public function getRates(Shipment $shipment): array
    {
        try {
            $token = $this->getAccessToken();

            // Validate and limit package dimensions
            $dimensions = $this->validatePackageDimensions(
                (float)$shipment->package_length,
                (float)$shipment->package_width,
                (float)$shipment->package_height,
                (float)$shipment->package_weight
            );

            $payload = [
                'accountNumber' => [
                    'value' => $this->accountNumber
                ],
                'requestedShipment' => [
                    'shipper' => [
                        'address' => [
                            'postalCode' => $shipment->sender_zipcode ?: '10001',
                            'countryCode' => 'US',
                            'stateOrProvinceCode' => $shipment->sender_state ?: $shipment->origin_state
                        ]
                    ],
                    'recipient' => [
                        'address' => [
                            'postalCode' => $shipment->recipient_postal_code,
                            'countryCode' => 'US',
                            'stateOrProvinceCode' => $shipment->recipient_state
                        ]
                    ],
                    'pickupType' => 'USE_SCHEDULED_PICKUP',
                    'packagingType' => 'YOUR_PACKAGING',
                    'rateRequestType' => ['LIST', 'ACCOUNT'],
                    'requestedPackageLineItems' => [
                        [
                            'weight' => [
                                'units' => $shipment->weight_unit ?? 'LB',
                                'value' => $dimensions['weight']
                            ],
                            'dimensions' => [
                                'length' => $dimensions['length'],
                                'width' => $dimensions['width'],
                                'height' => $dimensions['height'],
                                'units' => $shipment->dimension_unit ?? 'IN'
                            ]
                        ]
                    ]
                ]
            ];

            // Log the shipping rates payload
            Log::info('FedEx Shipping Rates Request Payload', [
                'shipment_id' => $shipment->id,
                'endpoint' => $this->baseUrl . '/rate/v1/rates/quotes',
                'payload' => $payload,
                'payload_json' => json_encode($payload, JSON_PRETTY_PRINT),
                'payload_size' => strlen(json_encode($payload)),
                'dimensions' => $dimensions,
                'shipment_details' => [
                    'sender_zipcode' => $shipment->sender_zipcode,
                    'sender_state' => $shipment->sender_state,
                    'recipient_postal_code' => $shipment->recipient_postal_code,
                    'recipient_state' => $shipment->recipient_state,
                    'weight_unit' => $shipment->weight_unit,
                    'dimension_unit' => $shipment->dimension_unit
                ]
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/rate/v1/rates/quotes', $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                
                // Log successful response
                Log::info('FedEx Shipping Rates Response', [
                    'shipment_id' => $shipment->id,
                    'status_code' => $response->status(),
                    'response_keys' => array_keys($responseData),
                    'has_rate_reply_details' => isset($responseData['output']['rateReplyDetails']),
                    'rate_count' => isset($responseData['output']['rateReplyDetails']) 
                        ? count($responseData['output']['rateReplyDetails']) 
                        : 0,
                    'response_data' => $responseData
                ]);
                
                return $this->processRateResponse($shipment, $responseData);
            }

            // Log error response
            Log::error('FedEx Rate API Error', [
                'shipment_id' => $shipment->id,
                'status_code' => $response->status(),
                'response_body' => $response->body(),
                'response_json' => $response->json()
            ]);
            
            throw new Exception('Failed to get FedEx rates: ' . $response->body());
        } catch (Exception $e) {
            Log::error('FedEx Rate Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process FedEx rate response and save to database
     */
    private function processRateResponse(Shipment $shipment, array $response): array
    {
        $rates = [];

        if (isset($response['output']['rateReplyDetails'])) {
            foreach ($response['output']['rateReplyDetails'] as $rateDetail) {
                $serviceType = $rateDetail['serviceType'] ?? 'UNKNOWN';

                // Filter to show only specific FedEx services
                $allowedServices = ['PRIORITY_OVERNIGHT', 'FEDEX_2_DAY', 'FEDEX_GROUND'];
                if (!in_array($serviceType, $allowedServices)) {
                    Log::info('Filtering out service type', [
                        'shipment_id' => $shipment->id,
                        'service_type' => $serviceType,
                        'reason' => 'Not in allowed services list'
                    ]);
                    continue;
                }

                // Skip if no rated shipment details
                if (!isset($rateDetail['ratedShipmentDetails']) || empty($rateDetail['ratedShipmentDetails'])) {
                    continue;
                }

                // Get the lowest rate from all rated shipment details
                $lowestRate = null;
                foreach ($rateDetail['ratedShipmentDetails'] as $ratedShipment) {
                    $currentRate = $ratedShipment['totalNetCharge'] ?? 0;
                    if ($lowestRate === null || $currentRate < $lowestRate) {
                        $lowestRate = $currentRate;
                    }
                }

                $baseRate = $lowestRate;
                $handlingFee = $baseRate * 0.10; // 10% handling fee
                $totalRate = $baseRate + $handlingFee;

                // Get transit time information
                $transitDays = null;
                if (isset($rateDetail['operationalDetail']['transitTime'])) {
                    $transitTime = $rateDetail['operationalDetail']['transitTime'];
                    // Convert transit time string to number of days
                    if (preg_match('/^(\d+)/', $transitTime, $matches)) {
                        $transitDays = (int)$matches[1];
                    }
                }

                $rate = ShipmentRate::create([
                    'shipment_id' => $shipment->id,
                    'service_type' => $serviceType,
                    'base_rate' => $baseRate,
                    'handling_fee' => $handlingFee,
                    'total_rate' => $totalRate,
                    'currency' => $rateDetail['ratedShipmentDetails'][0]['currency'] ?? 'USD',
                    'transit_days' => $transitDays,
                    'fedex_rate_response' => json_encode($rateDetail),
                ]);

                $rates[] = $rate;
            }
        }

        // Sort rates by price (lowest first)
        usort($rates, function ($a, $b) {
            return $a->total_rate <=> $b->total_rate;
        });

        return $rates;
    }

    /**
     * Check pickup availability
     */
    public function checkPickupAvailability(Shipment $shipment): array
    {
        try {
            $token = $this->getAccessToken();

            Log::info('Checking FedEx pickup availability', [
                'shipment_id' => $shipment->id,
                'pickup_address' => $shipment->sender_address_line
            ]);

            // Validate required address data
            if (empty($shipment->sender_address_line) || empty($shipment->sender_city) ||
                empty($shipment->sender_state) || empty($shipment->sender_zipcode)) {
                throw new \Exception('Missing required sender address information for pickup availability check');
            }

            $pickupAddress = $shipment->sender_address_line;
            $pickupCity = $shipment->sender_city;
            $pickupState = $shipment->sender_state;
            $pickupPostalCode = $shipment->sender_zipcode;

            // Format date for pickup check
            $pickupDate = $shipment->preferred_ship_date;
            if ($pickupDate->isPast()) {
                $pickupDate = \Carbon\Carbon::now()->addDay();
                if ($pickupDate->isWeekend()) {
                    $pickupDate = $pickupDate->next(\Carbon\Carbon::MONDAY);
                }
            }

            // Determine carrier code based on service type
            $serviceType = $shipment->selectedRate->service_type;
            $carrierCode = str_starts_with($serviceType, 'FEDEX_GROUND') ? 'FDXG' : 'FDXE';

            // Build payload according to FedEx Pickup Availability API
            $payload = [
                'associatedAccountNumber' => [
                    'value' => $this->accountNumber
                ],
                'pickupAddress' => [
                    'streetLines' => [$pickupAddress],
                    'city' => $pickupCity,
                    'stateOrProvinceCode' => $pickupState,
                    'postalCode' => $pickupPostalCode,
                    'countryCode' => 'US'
                ],
                'pickupRequestType' => [$pickupDate->isToday() ? 'SAME_DAY' : 'FUTURE_DAY'],
                'dispatchDate' => $pickupDate->format('Y-m-d'),
                'numberOfBusinessDays' => 1,
                'shipmentAttributes' => [
                    'serviceType' => $serviceType,
                    'packagingType' => 'YOUR_PACKAGING',
                    'dimensions' => [
                        'length' => min($shipment->package_length, 108),
                        'width' => min($shipment->package_width, 70),
                        'height' => min($shipment->package_height, 70),
                        'units' => 'IN'
                    ],
                    'weight' => [
                        'units' => 'LB',
                        'value' => min($shipment->package_weight, 150)
                    ]
                ],
                'carriers' => [$carrierCode],
                'countryRelationship' => 'DOMESTIC'
            ];

            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'X-locale' => 'en_US'
            ])->post($this->baseUrl . '/pickup/v1/pickup-availability', $payload);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('FedEx pickup availability checked successfully', [
                    'shipment_id' => $shipment->id,
                    'available' => !empty($data['output']['pickupAvailability'])
                ]);

                return [
                    'success' => true,
                    'available' => !empty($data['output']['pickupAvailability']),
                    'availability_data' => $data['output']['pickupAvailability'] ?? [],
                    'cutoff_time' => $data['output']['pickupAvailability'][0]['cutoffTime'] ?? null,
                    'access_time' => $data['output']['pickupAvailability'][0]['accessTime'] ?? null,
                    'message' => 'Pickup availability checked successfully',
                    'response_data' => $data
                ];
            }

            $errorBody = $response->body();
            Log::error('FedEx Pickup Availability API Error', [
                'shipment_id' => $shipment->id,
                'status_code' => $response->status(),
                'response_body' => $errorBody
            ]);

            return [
                'success' => false,
                'available' => false,
                'message' => 'Failed to check pickup availability',
                'error_code' => 'PICKUP_AVAILABILITY_ERROR'
            ];

        } catch (\Exception $e) {
            Log::error('FedEx Pickup Availability Error', [
                'shipment_id' => $shipment->id,
                'exception' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'available' => false,
                'message' => $e->getMessage(),
                'error_code' => 'PICKUP_AVAILABILITY_ERROR'
            ];
        }
    }

    /**
     * Track a package by tracking number
     */
    public function trackPackage(string $trackingNumber): array
    {
        try {
            $token = $this->getAccessToken();

            Log::info('Tracking FedEx package', [
                'tracking_number' => $trackingNumber
            ]);

            // Build payload according to FedEx Tracking API documentation
            $payload = [
                'trackingInfo' => [
                    [
                        'trackingNumberInfo' => [
                            'trackingNumber' => $trackingNumber
                        ]
                    ]
                ],
                'includeDetailedScans' => true
            ];

            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'X-locale' => 'en_US'
            ])->post($this->baseUrl . '/track/v1/trackingnumbers', $payload);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('FedEx tracking successful', [
                    'tracking_number' => $trackingNumber,
                    'has_events' => !empty($data['output']['completeTrackResults'][0]['trackResults'][0]['scanEvents'])
                ]);

                $trackResult = $data['output']['completeTrackResults'][0]['trackResults'][0] ?? null;

                if ($trackResult) {
                    $events = [];
                    if (isset($trackResult['scanEvents']) && is_array($trackResult['scanEvents'])) {
                        foreach ($trackResult['scanEvents'] as $event) {
                            $events[] = [
                                'timestamp' => $event['date'] . ' ' . $event['time'],
                                'description' => $event['eventDescription'] ?? 'No description',
                                'location' => $event['scanLocation']['city'] ?? 'Unknown location',
                                'status' => $event['eventType'] ?? 'UNKNOWN'
                            ];
                        }
                    }

                    return [
                        'success' => true,
                        'tracking_number' => $trackingNumber,
                        'status' => $trackResult['latestStatusDetail']['description'] ?? 'UNKNOWN',
                        'estimated_delivery' => $trackResult['deliveryDetails']['deliveryDate'] ?? null,
                        'events' => $events,
                        'response_data' => $data
                    ];
                }

                return [
                    'success' => false,
                    'message' => 'No tracking information found',
                    'error_code' => 'NO_TRACKING_DATA'
                ];
            }

            $errorBody = $response->body();
            Log::error('FedEx Tracking API Error', [
                'tracking_number' => $trackingNumber,
                'status_code' => $response->status(),
                'response_body' => $errorBody
            ]);

            // Try to parse error response
            $errorMessage = 'Failed to track package';
            try {
                $errorData = $response->json();
                if (isset($errorData['errors']) && is_array($errorData['errors']) && !empty($errorData['errors'])) {
                    $errorMessage .= ': ' . ($errorData['errors'][0]['message'] ?? 'Unknown error');
                }
            } catch (\Exception $parseException) {
                Log::error('Failed to parse FedEx tracking error response', [
                    'tracking_number' => $trackingNumber,
                    'exception' => $parseException->getMessage(),
                    'response_body' => $errorBody
                ]);
            }

            return [
                'success' => false,
                'message' => $errorMessage,
                'error_code' => 'TRACKING_ERROR'
            ];

        } catch (\Exception $e) {
            Log::error('FedEx Tracking Error', [
                'tracking_number' => $trackingNumber,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'TRACKING_ERROR'
            ];
        }
    }
}
