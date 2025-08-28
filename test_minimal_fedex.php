<?php

require_once 'vendor/autoload.php';

use App\Services\FedExServiceFixed;
use App\Models\Shipment;

// Initialize Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing Minimal FedEx Payload...\n";

try {
    $fedexService = new FedExServiceFixed();

    // Get access token using reflection to access private method
    $reflection = new ReflectionClass($fedexService);
    $method = $reflection->getMethod('getAccessToken');
    $method->setAccessible(true);
    $token = $method->invoke($fedexService);
    echo "Access token obtained successfully\n";

    // Create a minimal payload based on official FedEx example
    $minimalPayload = [
        'shipAction' => 'CONFIRM',
        'mergeLabelDocOption' => 'LABELS_AND_DOCS',
        'processingOptionType' => 'ALLOW_ASYNCHRONOUS',
        'oneLabelAtATime' => true,
        'labelResponseOptions' => 'LABEL',

        'accountNumber' => [
            'value' => '740561073'
        ],
        'requestedShipment' => [
            'shipper' => [
                'contact' => [
                    'personName' => 'John Taylor',
                    'emailAddress' => 'sample@company.com',
                    'phoneNumber' => '1234567890'
                ],
                'address' => [
                    'streetLines' => ['10 FedEx Parkway', 'Suite 302'],
                    'city' => 'Beverly Hills',
                    'stateOrProvinceCode' => 'CA',
                    'postalCode' => '90210',
                    'countryCode' => 'US',
                    'residential' => false
                ]
            ],
            'recipients' => [
                [
                    'contact' => [
                        'personName' => 'John Taylor',
                        'emailAddress' => 'sample@company.com',
                        'phoneNumber' => '1234567890'
                    ],
                    'address' => [
                        'streetLines' => ['10 FedEx Parkway', 'Suite 302'],
                        'city' => 'Beverly Hills',
                        'stateOrProvinceCode' => 'CA',
                        'postalCode' => '90210',
                        'countryCode' => 'US',
                        'residential' => false
                    ]
                ]
            ],
            'shipDatestamp' => '2025-07-14',
            'serviceType' => 'PRIORITY_OVERNIGHT',
            'packagingType' => 'YOUR_PACKAGING',
            'pickupType' => 'USE_SCHEDULED_PICKUP',
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
                        'value' => '10'
                    ],
                    'dimensions' => [
                        'length' => '10',
                        'width' => '10',
                        'height' => '10',
                        'units' => 'IN'
                    ],
                    'customerReferences' => [
                        [
                            'customerReferenceType' => 'CUSTOMER_REFERENCE',
                            'value' => 'Test Shipment'
                        ]
                    ],
                    'declaredValue' => [
                        'amount' => '100',
                        'currency' => 'USD'
                    ],
                    'itemDescription' => 'Test package'
                ]
            ],
            'totalPackageCount' => 1,
            'totalWeight' => [
                'units' => 'LB',
                'value' => '10'
            ],
            'totalDeclaredValue' => [
                'amount' => '100',
                'currency' => 'USD'
            ],
            'preferredCurrency' => 'USD',
            'rateRequestType' => ['LIST', 'PREFERRED']
        ]
    ];

    echo "Sending minimal payload...\n";
    echo "Payload size: " . strlen(json_encode($minimalPayload)) . " bytes\n";

    // Make the API request
    $response = \Illuminate\Support\Facades\Http::withHeaders([
        'Authorization' => 'Bearer ' . $token,
        'Content-Type' => 'application/json',
        'X-locale' => 'en_US'
    ])->post('https://apis-sandbox.fedex.com/ship/v1/shipments', $minimalPayload);

    echo "Response Status: " . $response->status() . "\n";
    echo "Response Body: " . $response->body() . "\n";

    if ($response->successful()) {
        echo "SUCCESS! Minimal payload worked.\n";
        $data = $response->json();
        print_r($data);
    } else {
        echo "FAILED! Minimal payload failed.\n";
        $errorData = $response->json();
        print_r($errorData);
    }

} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "Test completed.\n";
