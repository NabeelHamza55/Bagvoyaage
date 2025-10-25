<?php

/**
 * Test label display on Hostinger
 */

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TESTING LABEL DISPLAY ===\n\n";

// Test 1: Check if labels directory exists and is writable
echo "1. Checking labels directory...\n";
$labelsDir = storage_path('app/public/labels');
echo "   Directory: " . $labelsDir . "\n";
echo "   Exists: " . (is_dir($labelsDir) ? "YES" : "NO") . "\n";
echo "   Writable: " . (is_writable($labelsDir) ? "YES" : "NO") . "\n";

if (!is_dir($labelsDir)) {
    echo "   Creating directory...\n";
    mkdir($labelsDir, 0755, true);
    echo "   Created: " . (is_dir($labelsDir) ? "YES" : "NO") . "\n";
}

// Test 2: Check public storage link
echo "\n2. Checking public storage link...\n";
$publicLink = public_path('storage');
echo "   Public link: " . $publicLink . "\n";
echo "   Exists: " . (is_link($publicLink) || is_dir($publicLink) ? "YES" : "NO") . "\n";

if (!is_link($publicLink) && !is_dir($publicLink)) {
    echo "   Creating storage link...\n";
    try {
        \Artisan::call('storage:link');
        echo "   ✅ Storage link created\n";
    } catch (Exception $e) {
        echo "   ❌ Error creating link: " . $e->getMessage() . "\n";
    }
}

// Test 3: Check recent shipments
echo "\n3. Checking recent shipments...\n";
try {
    $shipments = \App\Models\Shipment::orderBy('id', 'desc')->limit(5)->get();
    foreach ($shipments as $shipment) {
        echo "   Shipment ID: " . $shipment->id . "\n";
        echo "   Tracking: " . $shipment->tracking_number . "\n";
        echo "   Status: " . $shipment->status . "\n";

        // Check if label file exists
        $labelFile = storage_path("app/public/labels/{$shipment->id}.pdf");
        echo "   Label file: " . $labelFile . "\n";
        echo "   File exists: " . (file_exists($labelFile) ? "YES" : "NO") . "\n";

        if (file_exists($labelFile)) {
            echo "   File size: " . filesize($labelFile) . " bytes\n";
            echo "   File readable: " . (is_readable($labelFile) ? "YES" : "NO") . "\n";
        }

        echo "   ---\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Test 4: Check URL accessibility
echo "\n4. Testing label URL accessibility...\n";
$baseUrl = config('app.url');
echo "   Base URL: " . $baseUrl . "\n";

try {
    $shipment = \App\Models\Shipment::orderBy('id', 'desc')->first();
    if ($shipment) {
        $labelUrl = $baseUrl . "/storage/labels/{$shipment->id}.pdf";
        echo "   Label URL: " . $labelUrl . "\n";

        // Test if URL is accessible
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'method' => 'HEAD'
            ]
        ]);

        $headers = @get_headers($labelUrl, 1, $context);
        if ($headers && strpos($headers[0], '200') !== false) {
            echo "   ✅ URL is accessible\n";
        } else {
            echo "   ❌ URL is not accessible\n";
        }
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== LABEL DISPLAY TEST COMPLETED ===\n";
echo "If labels are not showing:\n";
echo "1. Check storage permissions\n";
echo "2. Run: php artisan storage:link\n";
echo "3. Check if label files exist in storage/app/public/labels/\n";
echo "4. Check if public/storage symlink exists\n";
