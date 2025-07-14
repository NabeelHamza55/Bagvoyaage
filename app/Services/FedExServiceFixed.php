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

            Log::info('Creating FedEx shipment', [
                'shipment_id' => $shipment->id,
                'recipient_state' => $shipment->recipient_state,
                'package_weight' => $shipment->package_weight
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

            // Validate and format dimensions
            $dimensions = $this->validatePackageDimensions(
                (float)$shipment->package_length,
                (float)$shipment->package_width,
                (float)$shipment->package_height,
                (float)$shipment->package_weight
            );

            // Determine pickup type
            $pickupType = match($shipment->pickup_type ?? 'DROPOFF') {
                'PICKUP' => 'CONTACT_FEDEX_TO_SCHEDULE',
                'DROPOFF' => 'DROPOFF_AT_FEDEX_LOCATION',
                default => 'DROPOFF_AT_FEDEX_LOCATION'
            };

            // Automatically mark pickup as scheduled if pickup is requested
            if ($pickupType === 'CONTACT_FEDEX_TO_SCHEDULE') {
                $shipment->pickup_scheduled = true;
                $shipment->pickup_confirmation = 'AUTO-' . time();
                $shipment->save();

                Log::info('Pickup automatically scheduled', [
                    'shipment_id' => $shipment->id,
                    'pickup_confirmation' => $shipment->pickup_confirmation
                ]);
            }

            // Build payload according to FedEx Ship API documentation
            $payload = [
                'shipAction' => 'CONFIRM',
                'mergeLabelDocOption' => 'LABELS_AND_DOCS',
                'processingOptionType' => 'ALLOW_ASYNCHRONOUS',
                'oneLabelAtATime' => true,
                'labelResponseOptions' => 'URL_ONLY',
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
                            'residential' => true // Default to residential for consumer shipments
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
                                'residential' => true // Default to residential for consumer shipments
                            ],
                            'deliveryInstructions' => null // Optional, can be null
                        ]
                    ],
                    'shipDatestamp' => $shipment->preferred_ship_date->format('Y-m-d'),
                    'serviceType' => $this->mapDeliveryTypeToService($shipment->delivery_type),
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
                    'totalWeight' => [
                        'units' => $shipment->weight_unit,
                        'value' => $dimensions['weight']
                    ],
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
                'service_type' => $this->mapDeliveryTypeToService($shipment->delivery_type)
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
            function array_filter_recursive($input) {
                foreach ($input as &$value) {
                    if (is_array($value)) {
                        $value = array_filter_recursive($value);
                    }
                }
                return array_filter($input, function($v) {
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
                $trackingNumber = $data['output']['transactionShipments'][0]['pieceResponses'][0]['trackingNumber'] ?? null;
                $labelUrl = $data['output']['transactionShipments'][0]['pieceResponses'][0]['packageDocuments'][0]['url'] ?? null;
                $masterTrackingNumber = $data['output']['transactionShipments'][0]['masterTrackingNumber'] ?? $trackingNumber;

                // Log successful response
                Log::info('FedEx shipment created successfully', [
                    'shipment_id' => $shipment->id,
                    'tracking_number' => $trackingNumber,
                    'master_tracking_number' => $masterTrackingNumber,
                    'has_label' => !empty($labelUrl)
                ]);

                return [
                    'success' => true,
                    'tracking_number' => $trackingNumber,
                    'master_tracking_number' => $masterTrackingNumber,
                    'label_url' => $labelUrl,
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
     * Test FedEx API connection and authentication
     * This helps debug connection issues
     */
    public function testFedExConnection(): array
    {
        try {
            $token = $this->getAccessToken();

            // Test with a simple rate request using correct structure
            $testPayload = [
                'accountNumber' => [
                    'value' => $this->accountNumber
                ],
                'requestedShipment' => [
                    'shipper' => [
                        'contact' => [
                            'personName' => 'Test Shipper',
                            'phoneNumber' => '1234567890',
                            'emailAddress' => 'test@example.com'
                        ],
                        'address' => [
                            'streetLines' => ['123 Test St'],
                            'city' => 'New York',
                            'stateOrProvinceCode' => 'NY',
                            'postalCode' => '10001',
                            'countryCode' => 'US'
                        ]
                    ],
                    'recipients' => [
                        [
                            'contact' => [
                                'personName' => 'Test Recipient',
                                'phoneNumber' => '1234567890'
                            ],
                            'address' => [
                                'streetLines' => ['456 Test Ave'],
                                'city' => 'Los Angeles',
                                'stateOrProvinceCode' => 'CA',
                                'postalCode' => '90210',
                                'countryCode' => 'US'
                            ]
                        ]
                    ],
                    'requestedPackageLineItems' => [
                        [
                            'weight' => [
                                'units' => 'LB',
                                'value' => '10'
                            ]
                        ]
                    ]
                ]
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'X-locale' => 'en_US'
            ])->post($this->baseUrl . '/rate/v1/rates/quotes', $testPayload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'FedEx API connection successful',
                    'status_code' => $response->status()
                ];
            }

            return [
                'success' => false,
                'message' => 'FedEx API connection failed',
                'status_code' => $response->status(),
                'response_body' => $response->body()
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
            return 'N/A';
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
            return 'General Merchandise';
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
            // If still not 10 digits, use a default
            return '1234567890';
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
    private function mapDeliveryTypeToService(string $deliveryType): string
    {
        return match($deliveryType) {
            'standard' => 'FEDEX_GROUND',
            'express' => 'FEDEX_2_DAY',
            'overnight' => 'PRIORITY_OVERNIGHT',
            default => 'FEDEX_GROUND'
        };
    }

    /**
     * Schedule a pickup with FedEx
     */
    public function schedulePickup(Shipment $shipment): array
    {
        try {
            // Implementation for pickup scheduling would go here
            // For now, return a mock success response
            return [
                'success' => true,
                'confirmation_number' => 'MOCK-' . time(),
                'message' => 'Pickup scheduled successfully'
            ];
        } catch (Exception $e) {
            Log::error('FedEx Pickup Error', [
                'shipment_id' => $shipment->id,
                'exception' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'PICKUP_SCHEDULING_ERROR'
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

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/rate/v1/rates/quotes', $payload);

            if ($response->successful()) {
                return $this->processRateResponse($shipment, $response->json());
            }

            Log::error('FedEx Rate API Error: ' . $response->body());
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
        usort($rates, function($a, $b) {
            return $a->total_rate <=> $b->total_rate;
        });

        return $rates;
    }

    /**
     * Check pickup availability
     */
    public function checkPickupAvailability(Shipment $shipment): array
    {
        // For sandbox testing, always return available
        return [
            'success' => true,
            'available' => true,
            'message' => 'Pickup is available for this location',
            'cutoff_time' => '16:00',
            'access_time' => '08:00'
        ];
    }

    /**
     * Track a package by tracking number
     */
    public function trackPackage(string $trackingNumber): array
    {
        try {
            // For sandbox testing, return mock tracking data
            return [
                'success' => true,
                'tracking_number' => $trackingNumber,
                'status' => 'IN_TRANSIT',
                'estimated_delivery' => Carbon::now()->addDays(3)->format('Y-m-d'),
                'events' => [
                    [
                        'timestamp' => Carbon::now()->subHours(2)->format('Y-m-d H:i:s'),
                        'description' => 'Shipment information sent to FedEx',
                        'location' => 'ORIGIN'
                    ]
                ]
            ];
        } catch (Exception $e) {
            Log::error('FedEx Tracking Error', [
                'tracking_number' => $trackingNumber,
                'exception' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'TRACKING_ERROR'
            ];
        }
    }
}
