<?php

require_once 'vendor/autoload.php';

use App\Services\FedExServiceFixed;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$fedexService = new FedExServiceFixed();

// Test phone number formatting
$testPhones = [
    '11216896914',  // Should become 1216896914
    '11178243531',  // Should become 1178243531
    '1234567890',   // Should stay 1234567890
    '123456789',    // Should become 1234567890 (padded)
    '12345678901',  // Should become 2345678901 (truncated)
];

echo "Testing phone number formatting:\n";
foreach ($testPhones as $phone) {
    $formatted = $fedexService->formatPhoneNumber($phone);
    echo "Original: $phone -> Formatted: $formatted (length: " . strlen($formatted) . ")\n";
}

echo "\nTest completed.\n";
