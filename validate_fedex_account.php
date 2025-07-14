<?php

require_once 'vendor/autoload.php';

use App\Services\FedExServiceFixed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// Initialize Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== FedEx Account Validation ===\n\n";

try {
    $fedexService = new FedExServiceFixed();

    // Get configuration values
    $baseUrl = config('services.fedex.base_url');
    $apiKey = config('services.fedex.api_key');
    $secretKey = config('services.fedex.secret_key');
    $accountNumber = config('services.fedex.account_number');
    $meterNumber = config('services.fedex.meter_number');

    echo "Configuration Check:\n";
    echo "- Base URL: " . ($baseUrl ?: 'NOT SET') . "\n";
    echo "- API Key: " . ($apiKey ? substr($apiKey, 0, 10) . '...' : 'NOT SET') . "\n";
    echo "- Secret Key: " . ($secretKey ? substr($secretKey, 0, 10) . '...' : 'NOT SET') . "\n";
    echo "- Account Number: " . ($accountNumber ?: 'NOT SET') . "\n";
    echo "- Meter Number: " . ($meterNumber ?: 'NOT SET') . "\n\n";

    // Test 1: OAuth Token Generation
    echo "1. Testing OAuth Token Generation...\n";
    try {
        $response = Http::asForm()->post($baseUrl . '/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $apiKey,
            'client_secret' => $secretKey
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $token = $data['access_token'];
            echo "✅ OAuth Token Generated Successfully\n";
            echo "- Token Type: " . ($data['token_type'] ?? 'N/A') . "\n";
            echo "- Expires In: " . ($data['expires_in'] ?? 'N/A') . " seconds\n";
            echo "- Token Preview: " . substr($token, 0, 20) . "...\n\n";
        } else {
            echo "❌ OAuth Token Generation Failed\n";
            echo "- Status: " . $response->status() . "\n";
            echo "- Response: " . $response->body() . "\n\n";
            exit(1);
        }
    } catch (Exception $e) {
        echo "❌ OAuth Token Generation Exception: " . $e->getMessage() . "\n\n";
        exit(1);
    }

    // Test 2: Account Validation via Rate API
    echo "2. Testing Account Validation via Rate API...\n";
    try {
        $ratePayload = [
            'accountNumber' => [
                'value' => $accountNumber
            ],
            'requestedShipment' => [
                'shipper' => [
                    'address' => [
                        'streetLines' => ['123 Test Street'],
                        'city' => 'Test City',
                        'stateOrProvinceCode' => 'CA',
                        'postalCode' => '90210',
                        'countryCode' => 'US'
                    ]
                ],
                'recipient' => [
                    'address' => [
                        'streetLines' => ['456 Test Avenue'],
                        'city' => 'Test City',
                        'stateOrProvinceCode' => 'NY',
                        'postalCode' => '10001',
                        'countryCode' => 'US'
                    ]
                ],
                'pickupType' => 'DROPOFF_AT_FEDEX_LOCATION',
                'rateRequestType' => ['LIST', 'PREFERRED'],
                'requestedPackageLineItems' => [
                    [
                        'weight' => [
                            'units' => 'LB',
                            'value' => '1'
                        ],
                        'dimensions' => [
                            'length' => '5',
                            'width' => '5',
                            'height' => '5',
                            'units' => 'IN'
                        ]
                    ]
                ]
            ]
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'X-locale' => 'en_US'
        ])->post($baseUrl . '/rate/v1/rates/quotes', $ratePayload);

        if ($response->successful()) {
            echo "✅ Rate API Test Successful - Account is valid\n";
            $data = $response->json();
            if (isset($data['output']['rateReplyDetails'])) {
                echo "- Available Services: " . count($data['output']['rateReplyDetails']) . "\n";
            }
        } else {
            echo "❌ Rate API Test Failed\n";
            echo "- Status: " . $response->status() . "\n";
            echo "- Response: " . $response->body() . "\n";

            $errorData = $response->json();
            if (isset($errorData['errors'])) {
                foreach ($errorData['errors'] as $error) {
                    echo "- Error Code: " . ($error['code'] ?? 'N/A') . "\n";
                    echo "- Error Message: " . ($error['message'] ?? 'N/A') . "\n";
                }
            }
        }
        echo "\n";

    } catch (Exception $e) {
        echo "❌ Rate API Test Exception: " . $e->getMessage() . "\n\n";
    }

    // Test 3: Shipment API with Minimal Payload
    echo "3. Testing Shipment API with Minimal Payload...\n";
    try {
        $shipmentPayload = [
            'shipAction' => 'CONFIRM',
            'mergeLabelDocOption' => 'LABELS_AND_DOCS',
            'processingOptionType' => 'ALLOW_ASYNCHRONOUS',
            'oneLabelAtATime' => true,
            'labelResponseOptions' => 'URL_ONLY',
            'accountNumber' => [
                'value' => $accountNumber
            ],
            'requestedShipment' => [
                'shipper' => [
                    'contact' => [
                        'personName' => 'Test Sender',
                        'emailAddress' => 'test@example.com',
                        'phoneNumber' => '5551234567'
                    ],
                    'address' => [
                        'streetLines' => ['123 Test Street'],
                        'city' => 'Test City',
                        'stateOrProvinceCode' => 'CA',
                        'postalCode' => '90210',
                        'countryCode' => 'US',
                        'residential' => true
                    ]
                ],
                'recipients' => [
                    [
                        'contact' => [
                            'personName' => 'Test Recipient',
                            'phoneNumber' => '5559876543',
                            'emailAddress' => 'test@example.com'
                        ],
                        'address' => [
                            'streetLines' => ['456 Test Avenue'],
                            'city' => 'Test City',
                            'stateOrProvinceCode' => 'NY',
                            'postalCode' => '10001',
                            'countryCode' => 'US',
                            'residential' => true
                        ]
                    ]
                ],
                'shipDatestamp' => date('Y-m-d'),
                'serviceType' => 'PRIORITY_OVERNIGHT',
                'packagingType' => 'YOUR_PACKAGING',
                'pickupType' => 'DROPOFF_AT_FEDEX_LOCATION',
                'shippingChargesPayment' => [
                    'paymentType' => 'SENDER',
                    'payor' => [
                        'responsibleParty' => [
                            'accountNumber' => [
                                'value' => $accountNumber
                            ]
                        ]
                    ]
                ],
                'labelSpecification' => [
                    'labelStockType' => 'PAPER_7X475',
                    'imageType' => 'PDF'
                ],
                'requestedPackageLineItems' => [
                    [
                        'sequenceNumber' => 1,
                        'weight' => [
                            'units' => 'LB',
                            'value' => '1'
                        ],
                        'dimensions' => [
                            'length' => '5',
                            'width' => '5',
                            'height' => '5',
                            'units' => 'IN'
                        ],
                        'declaredValue' => [
                            'amount' => '10',
                            'currency' => 'USD'
                        ],
                        'itemDescription' => 'Test package'
                    ]
                ],
                'totalPackageCount' => 1,
                'totalWeight' => [
                    'units' => 'LB',
                    'value' => '1'
                ],
                'totalDeclaredValue' => [
                    'amount' => '10',
                    'currency' => 'USD'
                ],
                'preferredCurrency' => 'USD',
                'rateRequestType' => ['LIST', 'PREFERRED']
            ]
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'X-locale' => 'en_US'
        ])->post($baseUrl . '/ship/v1/shipments', $shipmentPayload);

        if ($response->successful()) {
            echo "✅ Shipment API Test Successful\n";
            $data = $response->json();
            if (isset($data['output']['transactionShipments'])) {
                echo "- Transaction ID: " . ($data['output']['transactionId'] ?? 'N/A') . "\n";
                echo "- Shipment Count: " . count($data['output']['transactionShipments']) . "\n";
            }
        } else {
            echo "❌ Shipment API Test Failed\n";
            echo "- Status: " . $response->status() . "\n";
            echo "- Response: " . $response->body() . "\n";

            $errorData = $response->json();
            if (isset($errorData['errors'])) {
                foreach ($errorData['errors'] as $error) {
                    echo "- Error Code: " . ($error['code'] ?? 'N/A') . "\n";
                    echo "- Error Message: " . ($error['message'] ?? 'N/A') . "\n";
                    if (isset($error['parameterList'])) {
                        foreach ($error['parameterList'] as $param) {
                            echo "  - Parameter: " . ($param['key'] ?? 'N/A') . " = " . ($param['value'] ?? 'N/A') . "\n";
                        }
                    }
                }
            }
        }
        echo "\n";

    } catch (Exception $e) {
        echo "❌ Shipment API Test Exception: " . $e->getMessage() . "\n\n";
    }

    // Test 4: Account Information API (if available)
    echo "4. Testing Account Information...\n";
    try {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'X-locale' => 'en_US'
        ])->get($baseUrl . '/account/v1/accounts/' . $accountNumber);

        if ($response->successful()) {
            echo "✅ Account Information Retrieved\n";
            $data = $response->json();
            print_r($data);
        } else {
            echo "❌ Account Information API Failed (This is normal for sandbox)\n";
            echo "- Status: " . $response->status() . "\n";
            echo "- Response: " . $response->body() . "\n";
        }
        echo "\n";

    } catch (Exception $e) {
        echo "❌ Account Information API Exception: " . $e->getMessage() . "\n\n";
    }

    // Summary
    echo "=== Validation Summary ===\n";
    echo "Account Number: $accountNumber\n";
    echo "Environment: " . (strpos($baseUrl, 'sandbox') !== false ? 'Sandbox' : 'Production') . "\n";
    echo "API Key: " . ($apiKey ? substr($apiKey, 0, 10) . '...' : 'NOT SET') . "\n";
    echo "\n";
    echo "If all tests pass except Shipment API, the account may not have shipment creation permissions.\n";
    echo "Contact FedEx to verify account setup and permissions.\n";

} catch (Exception $e) {
    echo "❌ Validation Failed: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\nValidation completed.\n";
