<?php

require_once 'vendor/autoload.php';

use App\Services\FedExServiceFixed;
use App\Models\Shipment;

// Initialize Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing FedEx with Realistic Data...\n";

try {
    $fedexService = new FedExServiceFixed();

    // Get access token using reflection to access private method
    $reflection = new ReflectionClass($fedexService);
    $method = $reflection->getMethod('getAccessToken');
    $method->setAccessible(true);
    $token = $method->invoke($fedexService);
    echo "Access token obtained successfully\n";

    // Create a realistic payload with valid data
    $realisticPayload = [
        'shipAction' => 'CONFIRM',
        'mergeLabelDocOption' => 'LABELS_AND_DOCS',
        'processingOptionType' => 'ALLOW_ASYNCHRONOUS',
        'oneLabelAtATime' => true,
        'labelResponseOptions' => 'URL_ONLY',
        'accountNumber' => [
            'value' => '740561073'
        ],
        'requestedShipment' => [
            'shipper' => [
                'contact' => [
                    'personName' => 'John Smith',
                    'emailAddress' => 'john.smith@example.com',
                    'phoneNumber' => '5551234567',
                    'companyName' => 'Smith Company',
                    'phoneExtension' => null,
                    'faxNumber' => null
                ],
                'address' => [
                    'streetLines' => ['123 Main Street'],
                    'city' => 'Los Angeles',
                    'stateOrProvinceCode' => 'CA',
                    'postalCode' => '90210',
                    'countryCode' => 'US',
                    'residential' => false
                ]
            ],
            'recipients' => [
                [
                    'contact' => [
                        'personName' => 'Jane Doe',
                        'phoneNumber' => '5559876543',
                        'emailAddress' => 'jane.doe@example.com',
                        'companyName' => 'Doe Corporation',
                        'phoneExtension' => null,
                        'faxNumber' => null
                    ],
                    'address' => [
                        'streetLines' => ['456 Oak Avenue'],
                        'city' => 'New York',
                        'stateOrProvinceCode' => 'NY',
                        'postalCode' => '10001',
                        'countryCode' => 'US',
                        'residential' => false
                    ],
                    'deliveryInstructions' => null
                ]
            ],
            'shipDatestamp' => '2025-07-14',
            'serviceType' => 'PRIORITY_OVERNIGHT',
            'packagingType' => 'YOUR_PACKAGING',
            'pickupType' => 'DROPOFF_AT_FEDEX_LOCATION',
            'shippingChargesPayment' => [
                'paymentType' => 'SENDER',
                'payor' => [
                    'responsibleParty' => [
                        'accountNumber' => [
                            'value' => '740561073'
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
                        'value' => '5'
                    ],
                    'dimensions' => [
                        'length' => '10',
                        'width' => '8',
                        'height' => '6',
                        'units' => 'IN'
                    ],
                    'customerReferences' => [
                        [
                            'customerReferenceType' => 'CUSTOMER_REFERENCE',
                            'value' => 'Test Shipment 001'
                        ]
                    ],
                    'declaredValue' => [
                        'amount' => '100',
                        'currency' => 'USD'
                    ],
                    'itemDescription' => 'Test package contents'
                ]
            ],
            'totalPackageCount' => 1,
            'totalWeight' => [
                'units' => 'LB',
                'value' => '5'
            ],
            'totalDeclaredValue' => [
                'amount' => '100',
                'currency' => 'USD'
            ],
            'preferredCurrency' => 'USD',
            'rateRequestType' => ['LIST', 'PREFERRED']
        ]
    ];

    echo "Sending realistic payload...\n";
    echo "Payload size: " . strlen(json_encode($realisticPayload)) . " bytes\n";

    // Make the API request
    $response = \Illuminate\Support\Facades\Http::withHeaders([
        'Authorization' => 'Bearer ' . $token,
        'Content-Type' => 'application/json',
        'X-locale' => 'en_US'
    ])->post('https://apis-sandbox.fedex.com/ship/v1/shipments', $realisticPayload);

    echo "Response Status: " . $response->status() . "\n";
    echo "Response Body: " . $response->body() . "\n";

    if ($response->successful()) {
        echo "SUCCESS! Realistic payload worked.\n";
        $data = $response->json();
        print_r($data);
    } else {
        echo "FAILED! Realistic payload failed.\n";
        $errorData = $response->json();
        print_r($errorData);
    }

} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "Test completed.\n";
