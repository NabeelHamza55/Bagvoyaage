<?php

require_once 'vendor/autoload.php';

use App\Services\FedExServiceFixed;
use App\Models\Shipment;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing FedEx API Connection...\n";

$fedexService = new FedExServiceFixed();

// Test basic connection
$connectionTest = $fedexService->testFedExConnection();
echo "Connection Test Result:\n";
print_r($connectionTest);

// Test with actual shipment data from the log
echo "\nTesting with actual shipment data...\n";

// Create a test shipment based on the log data
$testShipment = new Shipment();
$testShipment->id = 23;
$testShipment->sender_full_name = 'Avram Lane';
$testShipment->sender_email = 'gikes@mailinator.com';
$testShipment->sender_phone = '11216896914';
$testShipment->sender_address_line = 'Magna qui inventore';
$testShipment->sender_city = 'Los Angeles';
$testShipment->sender_state = 'CA';
$testShipment->sender_zipcode = '96105';
$testShipment->recipient_name = 'Regan Joseph';
$testShipment->recipient_phone = '11178243531';
$testShipment->recipient_address = 'Tempora in illo face';
$testShipment->recipient_city = 'New York';
$testShipment->recipient_state = 'NY';
$testShipment->recipient_postal_code = '10001';
$testShipment->package_length = 5;
$testShipment->package_width = 5;
$testShipment->package_height = 5;
$testShipment->package_weight = 28;
$testShipment->package_description = 'Est assumenda accus';
$testShipment->delivery_type = 'express';
$testShipment->pickup_type = 'DROPOFF';
$testShipment->packaging_type = 'YOUR_PACKAGING';
$testShipment->weight_unit = 'LB';
$testShipment->dimension_unit = 'IN';
$testShipment->currency_code = 'USD';
$testShipment->declared_value = 64.00;
$testShipment->preferred_ship_date = \Carbon\Carbon::parse('2025-07-13');

try {
    $result = $fedexService->createShipment($testShipment);
    echo "Shipment Creation Result:\n";
    print_r($result);
} catch (Exception $e) {
    echo "Error creating shipment: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\nTest completed.\n";
