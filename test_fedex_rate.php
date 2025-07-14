<?php

require_once 'vendor/autoload.php';

use App\Services\FedExServiceFixed;
use App\Models\Shipment;

// Initialize Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing FedEx Rate API...\n";

try {
    $fedexService = new FedExServiceFixed();

    // Get access token using reflection to access private method
    $reflection = new ReflectionClass($fedexService);
    $method = $reflection->getMethod('getAccessToken');
    $method->setAccessible(true);
    $token = $method->invoke($fedexService);
    echo "Access token obtained successfully\n";

    // Create a minimal rate request payload
    $ratePayload = [
        'accountNumber' => [
            'value' => '740561073'
        ],
        'requestedShipment' => [
            'shipper' => [
                'address' => [
                    'streetLines' => ['10 FedEx Parkway'],
                    'city' => 'Beverly Hills',
                    'stateOrProvinceCode' => 'CA',
                    'postalCode' => '90210',
                    'countryCode' => 'US'
                ]
            ],
            'recipient' => [
                'address' => [
                    'streetLines' => ['10 FedEx Parkway'],
                    'city' => 'Beverly Hills',
                    'stateOrProvinceCode' => 'CA',
                    'postalCode' => '90210',
                    'countryCode' => 'US'
                ]
            ],
            'pickupType' => 'DROPOFF_AT_FEDEX_LOCATION',
            'requestedPackageLineItems' => [
                [
                    'weight' => [
                        'units' => 'LB',
                        'value' => '10'
                    ],
                    'dimensions' => [
                        'length' => '10',
                        'width' => '10',
                        'height' => '10',
                        'units' => 'IN'
                    ]
                ]
            ]
        ]
    ];

    echo "Sending rate request...\n";
    echo "Payload size: " . strlen(json_encode($ratePayload)) . " bytes\n";

    // Make the rate API request
    $response = \Illuminate\Support\Facades\Http::withHeaders([
        'Authorization' => 'Bearer ' . $token,
        'Content-Type' => 'application/json',
        'X-locale' => 'en_US'
    ])->post('https://apis-sandbox.fedex.com/rate/v1/rates/quotes', $ratePayload);

    echo "Response Status: " . $response->status() . "\n";
    echo "Response Body: " . $response->body() . "\n";

    if ($response->successful()) {
        echo "SUCCESS! Rate request worked.\n";
        $data = $response->json();
        print_r($data);
    } else {
        echo "FAILED! Rate request failed.\n";
        $errorData = $response->json();
        print_r($errorData);
    }

} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "Test completed.\n";
