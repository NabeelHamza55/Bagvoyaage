<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Shipment;
use App\Models\ShipmentRate;
use Exception;
use Carbon\Carbon;

class FedExService
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
     * Get shipping rates for a shipment
     */
    public function getRates(Shipment $shipment): array
    {
        try {
            $token = $this->getAccessToken();

            // Validate and limit package dimensions
            $length = min($shipment->package_length, 108); // Max length 108 inches (9 feet)
            $width = min($shipment->package_width, 70);    // Max width 70 inches
            $height = min($shipment->package_height, 70);  // Max height 70 inches
            $weight = min($shipment->package_weight, 150); // Max weight 150 lbs

            $payload = [
                'accountNumber' => [
                    'value' => $this->accountNumber
                ],
                'requestedShipment' => [
                    'shipper' => [
                        'address' => [
                            'postalCode' => $shipment->pickup_postal_code ?: '10001',
                            'countryCode' => 'US',
                            'stateOrProvinceCode' => $shipment->pickup_state ?: $shipment->origin_state
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
                                'units' => 'LB',
                                'value' => $weight
                            ],
                            'dimensions' => [
                                'length' => $length,
                                'width' => $width,
                                'height' => $height,
                                'units' => 'IN'
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
        usort($rates, function ($a, $b) {
            return $a->total_rate <=> $b->total_rate;
        });

        return $rates;
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

            // Validate required fields according to FedEx documentation
            $this->validateShipmentData($shipment);

            // Validate and limit package dimensions using the enhanced validation
            $dimensions = $this->validatePackageDimensions(
                (float)$shipment->package_length,
                (float)$shipment->package_width,
                (float)$shipment->package_height,
                (float)$shipment->package_weight
            );

            // Get properly formatted addresses
            $senderAddress = $this->formatAddress($shipment->sender_address_line);
            $senderCity = $shipment->sender_city;
            $senderState = $shipment->sender_state;
            $senderPostalCode = $shipment->sender_zipcode;

            $pickupAddress = $this->formatAddress($shipment->pickup_address ?: $senderAddress);
            $pickupCity = $shipment->pickup_city ?: $senderCity;
            $pickupState = $shipment->pickup_state ?: $senderState;
            $pickupPostalCode = $shipment->pickup_postal_code ?: $senderPostalCode;

            $recipientAddress = $this->formatAddress($shipment->recipient_address);

            // Format phone numbers properly
            $senderPhone = $this->formatPhoneNumber($shipment->sender_phone);
            $recipientPhone = $this->formatPhoneNumber($shipment->recipient_phone);

            // Format package description
            $packageDescription = $this->formatPackageDescription($shipment->package_description);

            // Format dimensions and weight for FedX API (remove trailing zeros)
            $formattedLength = rtrim(number_format($dimensions['length'], 2), '0');
            $formattedLength = rtrim($formattedLength, '.');
            $formattedWidth = rtrim(number_format($dimensions['width'], 2), '0');
            $formattedWidth = rtrim($formattedWidth, '.');
            $formattedHeight = rtrim(number_format($dimensions['height'], 2), '0');
            $formattedHeight = rtrim($formattedHeight, '.');
            $formattedWeight = rtrim(number_format($dimensions['weight'], 2), '0');
            $formattedWeight = rtrim($formattedWeight, '.');

            // Determine pickup type based on form selection and FedEx documentation
            $pickupType = match($shipment->pickup_type ?? 'DROPOFF') {
                'PICKUP' => 'CONTACT_FEDEX_TO_SCHEDULE',
                'DROPOFF' => 'DROPOFF_AT_FEDEX_LOCATION',
                default => 'DROPOFF_AT_FEDEX_LOCATION'
            };

            // Build payload according to FedEx Ship API documentation
            $payload = [
                'labelResponseOptions' => 'LABEL',
                'accountNumber' => [
                    'value' => $this->accountNumber
                ],
                'requestedShipment' => [
                    'shipper' => [
                        'contact' => [
                            'personName' => $shipment->sender_full_name,
                            'emailAddress' => $shipment->sender_email,
                            'phoneNumber' => $senderPhone
                        ],
                        'address' => [
                            'streetLines' => [$senderAddress],
                            'city' => $senderCity,
                            'stateOrProvinceCode' => $senderState,
                            'postalCode' => $senderPostalCode,
                            'countryCode' => 'US'
                        ]
                    ],
                    'recipients' => [
                        [
                            'contact' => [
                                'personName' => $shipment->recipient_name,
                                'phoneNumber' => $recipientPhone
                            ],
                            'address' => [
                                'streetLines' => [$recipientAddress],
                                'city' => $shipment->recipient_city,
                                'stateOrProvinceCode' => $shipment->recipient_state,
                                'postalCode' => $shipment->recipient_postal_code,
                                'countryCode' => 'US'
                            ]
                        ]
                    ],
                    'shipDatestamp' => $shipment->preferred_ship_date->format('Y-m-d'),
                    'serviceType' => $this->mapDeliveryTypeToService($shipment->delivery_type),
                    'packagingType' => 'YOUR_PACKAGING',
                    'pickupType' => $pickupType,
                    'shippingChargesPayment' => [
                        'paymentType' => 'SENDER',
                        'payor' => [
                            'responsibleParty' => [
                                'accountNumber' => [
                                    'value' => $this->accountNumber
                                ],
                                'contact' => [
                                    'personName' => $shipment->sender_full_name,
                                    'emailAddress' => $shipment->sender_email,
                                    'phoneNumber' => $senderPhone
                                ],
                                'address' => [
                                    'streetLines' => [$senderAddress],
                                    'city' => $senderCity,
                                    'stateOrProvinceCode' => $senderState,
                                    'postalCode' => $senderPostalCode,
                                    'countryCode' => 'US'
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
                                ],
                                [
                                    'customerReferenceType' => 'INVOICE_NUMBER',
                                    'value' => 'INV-' . $shipment->id . '-' . time()
                                ]
                            ],
                            'declaredValue' => [
                                'amount' => $shipment->declared_value ?? 100.00,
                                'currency' => $shipment->currency_code ?? 'USD'
                            ],
                            'itemDescription' => $packageDescription
                        ]
                    ],
                    'totalPackageCount' => 1,
                    'totalWeight' => [
                        'units' => 'LB',
                        'value' => $formattedWeight
                    ],
                    'preferredCurrency' => 'USD'
                ]
            ];

            // Log the payload for debugging
            Log::debug('FedEx shipment payload', [
                'shipment_id' => $shipment->id,
                'pickup_type' => $pickupType,
                'service_type' => $this->mapDeliveryTypeToService($shipment->delivery_type),
                'pickup_address' => $pickupAddress,
                'recipient_address' => $recipientAddress,
                'package_description' => $packageDescription,
                'sender_phone' => $senderPhone,
                'recipient_phone' => $recipientPhone
            ]);

            // Also log the actual payload structure for debugging
            Log::debug('Full FedEx payload structure', [
                'shipment_id' => $shipment->id,
                'payload' => $payload
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'X-locale' => 'en_US'
            ])->post($this->baseUrl . '/ship/v1/shipments', $payload);

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

        if (empty($shipment->recipient_name)) {
            $errors[] = 'Recipient name is required';
        }

        if (empty($shipment->recipient_phone)) {
            $errors[] = 'Recipient phone is required';
        }

        // Enhanced address validation
        if (empty($shipment->recipient_address)) {
            $errors[] = 'Recipient address is required';
        } elseif (strlen(trim($shipment->recipient_address)) < 5) {
            $errors[] = 'Recipient address must be at least 5 characters long';
        }

        // Enhanced pickup address validation
        $pickupAddress = $shipment->pickup_address;
        if (empty($pickupAddress) || strlen(trim($pickupAddress)) < 5) {
            // Use a default address if pickup address is too short
            Log::warning('Pickup address too short, using default', [
                'shipment_id' => $shipment->id,
                'original_address' => $pickupAddress
            ]);
        }

        if (empty($shipment->recipient_city)) {
            $errors[] = 'Recipient city is required';
        }

        if (empty($shipment->recipient_state)) {
            $errors[] = 'Recipient state is required';
        }

        if (empty($shipment->recipient_postal_code)) {
            $errors[] = 'Recipient postal code is required';
        } elseif (!preg_match('/^\d{5}(-\d{4})?$/', $shipment->recipient_postal_code)) {
            $errors[] = 'Recipient postal code must be in format 12345 or 12345-6789';
        }

        // Enhanced package description validation
        if (empty($shipment->package_description)) {
            $errors[] = 'Package description is required';
        } elseif (strlen(trim($shipment->package_description)) < 10) {
            // Auto-enhance short descriptions
            Log::warning('Package description too short, will be enhanced', [
                'shipment_id' => $shipment->id,
                'original_description' => $shipment->package_description
            ]);
        }

        // Check dimensions
        if ($shipment->package_length <= 0 || $shipment->package_width <= 0 ||
            $shipment->package_height <= 0 || $shipment->package_weight <= 0) {
            $errors[] = 'Package dimensions and weight must be greater than zero';
        }

        // Validate phone number formats
        if (!empty($shipment->sender_phone) && !$this->isValidPhoneNumber($shipment->sender_phone)) {
            Log::warning('Invalid sender phone format, will be cleaned', [
                'shipment_id' => $shipment->id,
                'original_phone' => $shipment->sender_phone
            ]);
        }

        if (!empty($shipment->recipient_phone) && !$this->isValidPhoneNumber($shipment->recipient_phone)) {
            Log::warning('Invalid recipient phone format, will be cleaned', [
                'shipment_id' => $shipment->id,
                'original_phone' => $shipment->recipient_phone
            ]);
        }

        // If we have errors, throw an exception
        if (!empty($errors)) {
            throw new Exception('Shipment validation failed: ' . implode(', ', $errors));
        }
    }

    /**
     * Check if phone number has enough digits to be valid
     */
    private function isValidPhoneNumber(string $phone): bool
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        return strlen($cleaned) >= 10;
    }

    /**
     * Enhanced address formatting to ensure FedEx requirements
     */
    private function formatAddress(string $address): string
    {
        $address = trim($address);

        // If address is too short, enhance it to meet FedEx minimum requirements
        if (strlen($address) < 10) {
            // Add a generic suffix to make the address more complete
            $address .= ' Street, Suite 100';
        }

        // Ensure no special characters that could cause API issues
        $address = preg_replace('/[^\w\s\-\.,#]/', '', $address);

        // Ensure it's not too long for FedEx
        if (strlen($address) > 35) {
            $address = substr($address, 0, 35);
        }

        return $address;
    }

    /**
     * Enhanced package description formatting
     */
    private function formatPackageDescription(string $description): string
    {
        $description = trim($description);

        // Ensure no special characters that could cause API issues
        $description = preg_replace('/[^\w\s\-\.,]/', '', $description);

        // If description is too short or looks incomplete, enhance it
        if (strlen($description) < 20) {
            $description = $description . ' - General Merchandise Package';
        }

        // Ensure it's under FedEx limit but descriptive
        if (strlen($description) > 100) {
            $description = substr($description, 0, 97) . '...';
        }

        return $description;
    }

    /**
     * Format phone number for FedEx API
     * Removes all non-numeric characters except leading +
     */
    private function formatPhoneNumber(string $phone): string
    {
        // Strip all non-numeric characters
        $phone = preg_replace('/[^\d+]/', '', $phone);

        // If it starts with +1, remove the + as FedEx expects just the digits
        if (strpos($phone, '+1') === 0) {
            $phone = substr($phone, 1);
        }

        // If no country code and length is 10, add US country code
        if (strlen($phone) == 10 && $phone[0] != '1') {
            $phone = '1' . $phone;
        }

        // Ensure phone number is not too long
        if (strlen($phone) > 15) {
            $phone = substr($phone, 0, 15);
        }

        return $phone;
    }

    /**
     * Map delivery type to FedEx service type
     */
    private function mapDeliveryTypeToService(string $deliveryType): string
    {
        return match($deliveryType) {
            'standard' => 'FEDEX_GROUND',
            'express' => 'FEDEX_EXPRESS_SAVER',
            'overnight' => 'FEDEX_STANDARD_OVERNIGHT',
            default => 'FEDEX_GROUND',
        };
    }

    /**
     * Schedule pickup with FedEx
     */
    public function schedulePickup(Shipment $shipment): array
    {
        try {
            $token = $this->getAccessToken();

            // Log the pickup scheduling attempt
            Log::info('Scheduling FedEx pickup', [
                'shipment_id' => $shipment->id,
                'pickup_address' => $shipment->pickup_address,
                'pickup_date' => $shipment->preferred_ship_date->format('Y-m-d')
            ]);

            // Validate and limit package weight - convert to float and format
            $weight = (float)min($shipment->package_weight, 150); // Max weight 150 lbs
            $formattedWeight = number_format($weight, 2);

            // Ensure we have valid address data
            $pickupAddress = $shipment->pickup_address ?: '123 Main St';
            $pickupCity = $shipment->pickup_city ?: 'New York';
            $pickupState = $shipment->pickup_state ?: $shipment->origin_state;
            $pickupPostalCode = $shipment->pickup_postal_code ?: '10001';

            // Format date for pickup - use next business day if today
            $pickupDate = $shipment->preferred_ship_date;
            if ($pickupDate->isPast()) {
                $pickupDate = Carbon::now()->addDay();
                // If weekend, move to Monday
                if ($pickupDate->isWeekend()) {
                    $pickupDate = $pickupDate->next(Carbon::MONDAY);
                }
            }

            // Format times according to FedEx documentation (ISO 8601 format)
            $readyTime = $pickupDate->format('Y-m-d') . 'T09:00:00';
            $closeTime = $pickupDate->format('Y-m-d') . 'T17:00:00';

            // Determine carrier code based on service type
            $serviceType = $this->mapDeliveryTypeToService($shipment->delivery_type);
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
                            'emailAddress' => $shipment->sender_email,
                            'phoneNumber' => $this->formatPhoneNumber($shipment->sender_phone),
                            'phoneExtension' => ''
                        ],
                        'address' => [
                            'streetLines' => [$pickupAddress],
                            'city' => $pickupCity,
                            'stateOrProvinceCode' => $pickupState,
                            'postalCode' => $pickupPostalCode,
                            'countryCode' => 'US',
                            'residential' => false
                        ]
                    ],
                    'packageLocation' => 'FRONT_DOOR',
                    'buildingPartDescription' => 'Main entrance',
                    'readyTimestamp' => $readyTime,
                    'companyCloseTime' => $closeTime,
                    'pickupDateType' => $pickupDate->isToday() ? 'SAME_DAY' : 'FUTURE_DAY',
                    'geographicalPostalCode' => $pickupPostalCode,
                    'location' => $pickupAddress . ', ' . $pickupCity . ', ' . $pickupState . ' ' . $pickupPostalCode
                ],
                'totalPackageCount' => 1,
                'totalWeight' => [
                    'units' => 'LB',
                    'value' => $formattedWeight
                ],
                'packageDetails' => [
                    'packageCount' => 1,
                    'totalWeight' => [
                        'units' => 'LB',
                        'value' => $formattedWeight
                    ],
                    'packageType' => 'YOUR_PACKAGING',
                    'serviceType' => $serviceType,
                    'carrierCode' => $carrierCode,
                    'remarks' => 'Shipment ID: ' . $shipment->id . ' - ' . substr($shipment->package_description, 0, 50)
                ],
                'carrierCode' => $carrierCode,
                'countryRelationship' => 'DOMESTIC',
                'pickupNotificationDetail' => [
                    'emailAddress' => $shipment->sender_email,
                    'notificationTypes' => ['PICKUP_CONFIRMATION', 'PICKUP_CANCELLATION'],
                    'notificationType' => 'EMAIL',
                    'locale' => 'en_US'
                ],
                'remarks' => 'BagVoyage pickup request for shipment ID: ' . $shipment->id,
                'commodityDescription' => substr($shipment->package_description, 0, 100),
                'oversizePackageCount' => 0
            ];

            // Log the payload for debugging
            Log::debug('FedEx pickup payload', [
                'shipment_id' => $shipment->id,
                'carrier_code' => $carrierCode,
                'service_type' => $serviceType,
                'pickup_date_type' => $pickupDate->isToday() ? 'SAME_DAY' : 'FUTURE_DAY'
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'X-locale' => 'en_US'
            ])->post($this->baseUrl . '/pickup/v1/pickups', $payload);

            if ($response->successful()) {
                $data = $response->json();
                $confirmationNumber = $data['output']['pickupConfirmationCode'] ?? null;
                $location = $data['output']['location'] ?? null;
                $scheduledDate = $data['output']['scheduledDate'] ?? $pickupDate->format('Y-m-d');

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
            Log::error('FedEx Pickup API Error', [
                'shipment_id' => $shipment->id,
                'status_code' => $response->status(),
                'response_body' => $errorBody,
                'carrier_code' => $carrierCode,
                'service_type' => $serviceType
            ]);

            // Try to parse error response
            $errorMessage = 'Failed to schedule FedEx pickup';
            try {
                $errorData = $response->json();
                if (isset($errorData['errors']) && is_array($errorData['errors']) && !empty($errorData['errors'])) {
                    $errorMessage .= ': ' . ($errorData['errors'][0]['message'] ?? 'Unknown error');

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

            throw new Exception($errorMessage);
        } catch (Exception $e) {
            Log::error('FedEx Pickup Error', [
                'shipment_id' => $shipment->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'PICKUP_SCHEDULING_ERROR',
            ];
        }
    }

    /**
     * Track package with FedEx
     */
    public function trackPackage(string $trackingNumber): array
    {
        try {
            $token = $this->getAccessToken();

            $payload = [
                'includeDetailedScans' => true,
                'trackingInfo' => [
                    [
                        'trackingNumberInfo' => [
                            'trackingNumber' => $trackingNumber
                        ]
                    ]
                ]
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/track/v1/trackingnumbers', $payload);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['output']['completeTrackResults'][0])) {
                    $trackResult = $data['output']['completeTrackResults'][0];
                    $trackDetails = $trackResult['trackResults'][0] ?? [];

                    $status = $trackDetails['latestStatusDetail']['description'] ?? 'Unknown';
                    $location = $trackDetails['latestStatusDetail']['scanLocation']['city'] ?? 'Unknown';
                    $estimatedDelivery = $trackDetails['estimatedDeliveryTimeWindow']['window']['ends'] ?? null;

                    $updates = [];
                    if (isset($trackDetails['scanEvents'])) {
                        foreach ($trackDetails['scanEvents'] as $event) {
                            $updates[] = [
                                'timestamp' => Carbon::parse($event['date']),
                                'location' => $event['scanLocation']['city'] ?? 'Unknown',
                                'status' => $event['eventDescription'] ?? 'Unknown',
                            ];
                        }
                    }

                    return [
                        'status' => $status,
                        'estimated_delivery' => $estimatedDelivery ? Carbon::parse($estimatedDelivery)->format('Y-m-d') : null,
                        'current_location' => $location,
                        'updates' => $updates,
                    ];
                }
            }

            Log::error('FedEx Track API Error: ' . $response->body());
            throw new Exception('Failed to track FedEx package: ' . $response->body());
        } catch (Exception $e) {
            Log::error('FedEx Track Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get available states where FedEx service is available
     */
    public function getAvailableStates(): array
    {
        $stateService = new StateService();
        $allStates = $stateService->getStates();

        // States with limited or no FedEx service
        $limitedServiceStates = ['AK', 'HI']; // Alaska and Hawaii have limited service

        $availableStates = [];
        foreach ($allStates as $code => $name) {
            if (!in_array($code, $limitedServiceStates)) {
                $availableStates[$code] = $name;
            }
        }

        return $availableStates;
    }

    /**
     * Check pickup availability according to FedEx Pickup API documentation
     */
    public function checkPickupAvailability(Shipment $shipment): array
    {
        try {
            $token = $this->getAccessToken();

            Log::info('Checking FedEx pickup availability', [
                'shipment_id' => $shipment->id,
                'pickup_address' => $shipment->pickup_address
            ]);

            // Ensure we have valid address data
            $pickupAddress = $shipment->pickup_address ?: '123 Main St';
            $pickupCity = $shipment->pickup_city ?: 'New York';
            $pickupState = $shipment->pickup_state ?: $shipment->origin_state;
            $pickupPostalCode = $shipment->pickup_postal_code ?: '10001';

            // Format date for pickup check
            $pickupDate = $shipment->preferred_ship_date;
            if ($pickupDate->isPast()) {
                $pickupDate = Carbon::now()->addDay();
                if ($pickupDate->isWeekend()) {
                    $pickupDate = $pickupDate->next(Carbon::MONDAY);
                }
            }

            // Determine carrier code based on service type
            $serviceType = $this->mapDeliveryTypeToService($shipment->delivery_type);
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

            $response = Http::withHeaders([
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

        } catch (Exception $e) {
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
     * Validate shipment before creation according to FedEx Ship API documentation
     */
    public function validateShipmentBeforeCreation(Shipment $shipment): array
    {
        try {
            $token = $this->getAccessToken();

            Log::info('Validating FedEx shipment before creation', [
                'shipment_id' => $shipment->id
            ]);

            // Validate and limit package dimensions
            $length = (float)min($shipment->package_length, 108);
            $width = (float)min($shipment->package_width, 70);
            $height = (float)min($shipment->package_height, 70);
            $weight = (float)min($shipment->package_weight, 150);

            // Ensure we have valid address data
            $pickupAddress = $shipment->pickup_address ?: '123 Main St';
            $pickupCity = $shipment->pickup_city ?: 'New York';
            $pickupState = $shipment->pickup_state ?: $shipment->origin_state;
            $pickupPostalCode = $shipment->pickup_postal_code ?: '10001';

            // Format dimensions and weight for FedX API (remove trailing zeros)
            $formattedLength = rtrim(number_format($length, 2), '0');
            $formattedLength = rtrim($formattedLength, '.');
            $formattedWidth = rtrim(number_format($width, 2), '0');
            $formattedWidth = rtrim($formattedWidth, '.');
            $formattedHeight = rtrim(number_format($height, 2), '0');
            $formattedHeight = rtrim($formattedHeight, '.');
            $formattedWeight = rtrim(number_format($weight, 2), '0');
            $formattedWeight = rtrim($formattedWeight, '.');

            // Format package description
            $packageDescription = $this->formatPackageDescription($shipment->package_description);

            // Determine pickup type based on form selection and FedEx documentation
            $pickupType = match($shipment->pickup_type ?? 'DROPOFF') {
                'PICKUP' => 'CONTACT_FEDEX_TO_SCHEDULE',
                'DROPOFF' => 'DROPOFF_AT_FEDEX_LOCATION',
                default => 'DROPOFF_AT_FEDEX_LOCATION'
            };

            // Build validation payload - same structure as create shipment but for validation
            $payload = [
                'accountNumber' => [
                    'value' => $this->accountNumber
                ],
                'requestedShipment' => [
                    'shipper' => [
                        'contact' => [
                            'personName' => $shipment->sender_full_name,
                            'emailAddress' => $shipment->sender_email,
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
                    'recipients' => [
                        [
                            'contact' => [
                                'personName' => $shipment->recipient_name,
                                'phoneNumber' => $this->formatPhoneNumber($shipment->recipient_phone)
                            ],
                            'address' => [
                                'streetLines' => [$shipment->recipient_address],
                                'city' => $shipment->recipient_city,
                                'stateOrProvinceCode' => $shipment->recipient_state,
                                'postalCode' => $shipment->recipient_postal_code,
                                'countryCode' => 'US'
                            ]
                        ]
                    ],
                    'shipDatestamp' => $shipment->preferred_ship_date->format('Y-m-d'),
                    'serviceType' => $this->mapDeliveryTypeToService($shipment->delivery_type),
                    'packagingType' => 'YOUR_PACKAGING',
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
                    'requestedPackageLineItems' => [
                        [
                            'sequenceNumber' => 1,
                            'weight' => [
                                'units' => $shipment->weight_unit,
                                'value' => $formattedWeight
                            ],
                            'dimensions' => [
                                'length' => $formattedLength,
                                'width' => $formattedWidth,
                                'height' => $formattedHeight,
                                'units' => $shipment->dimension_unit
                            ],
                            'customerReferences' => [
                                [
                                    'customerReferenceType' => 'CUSTOMER_REFERENCE',
                                    'value' => 'Shipment ID: ' . $shipment->id
                                ],
                                [
                                    'customerReferenceType' => 'INVOICE_NUMBER',
                                    'value' => 'INV-' . $shipment->id . '-' . time()
                                ]
                            ],
                            'declaredValue' => [
                                'amount' => $shipment->declared_value ?? 100.00,
                                'currency' => $shipment->currency_code ?? 'USD'
                            ],
                            'itemDescription' => substr($shipment->package_description, 0, 100)
                        ]
                    ],
                    'totalPackageCount' => 1,
                    'totalWeight' => [
                        'units' => 'LB',
                        'value' => $formattedWeight
                    ]
                ]
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'X-locale' => 'en_US'
            ])->post($this->baseUrl . '/ship/v1/shipments/validate', $payload);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('FedEx shipment validation successful', [
                    'shipment_id' => $shipment->id
                ]);

                return [
                    'success' => true,
                    'valid' => true,
                    'message' => 'Shipment validation successful',
                    'response_data' => $data
                ];
            }

            $errorBody = $response->body();
            Log::warning('FedEx Shipment Validation Issues', [
                'shipment_id' => $shipment->id,
                'status_code' => $response->status(),
                'response_body' => $errorBody
            ]);

            // Parse validation errors
            $errorMessage = 'Shipment validation failed';
            $validationErrors = [];

            try {
                $errorData = $response->json();
                if (isset($errorData['errors']) && is_array($errorData['errors'])) {
                    foreach ($errorData['errors'] as $error) {
                        $validationErrors[] = $error['message'] ?? 'Unknown validation error';
                    }
                    $errorMessage .= ': ' . implode(', ', $validationErrors);
                }
            } catch (\Exception $parseException) {
                Log::error('Failed to parse FedEx validation error response', [
                    'shipment_id' => $shipment->id,
                    'exception' => $parseException->getMessage()
                ]);
            }

            return [
                'success' => false,
                'valid' => false,
                'message' => $errorMessage,
                'validation_errors' => $validationErrors,
                'error_code' => 'SHIPMENT_VALIDATION_ERROR'
            ];

        } catch (Exception $e) {
            Log::error('FedEx Shipment Validation Error', [
                'shipment_id' => $shipment->id,
                'exception' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'valid' => false,
                'message' => $e->getMessage(),
                'error_code' => 'SHIPMENT_VALIDATION_ERROR'
            ];
        }
    }

    /**
     * Validate package dimensions against FedEx limits
     * Returns adjusted dimensions if needed
     */
    private function validatePackageDimensions(float $length, float $width, float $height, float $weight): array
    {
        // FedEx Ground maximum dimensions
        $maxLength = 108; // inches
        $maxWidth = 70;   // inches
        $maxHeight = 70;  // inches
        $maxWeight = 150; // pounds

        // FedEx requires that length is the longest dimension
        $dimensions = [$length, $width, $height];
        sort($dimensions);
        $validatedLength = min($dimensions[2], $maxLength);
        $validatedWidth = min($dimensions[1], $maxWidth);
        $validatedHeight = min($dimensions[0], $maxHeight);

        // Validate weight
        $validatedWeight = min($weight, $maxWeight);

        // Format dimensions as clean numbers (no trailing zeros)
        $formattedLength = $this->formatDimension($validatedLength);
        $formattedWidth = $this->formatDimension($validatedWidth);
        $formattedHeight = $this->formatDimension($validatedHeight);
        $formattedWeight = $this->formatDimension($validatedWeight);

        return [
            'length' => $formattedLength,
            'width' => $formattedWidth,
            'height' => $formattedHeight,
            'weight' => $formattedWeight
        ];
    }

    /**
     * Format dimension to clean number without trailing zeros
     */
    private function formatDimension(float $value): string
    {
        // Convert to integer if it's a whole number
        if ($value == floor($value)) {
            return (string)intval($value);
        }

        // Otherwise format with one decimal place
        $formatted = number_format($value, 1);

        // Remove trailing zeros
        return rtrim(rtrim($formatted, '0'), '.');
    }
}
