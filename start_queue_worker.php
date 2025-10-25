<?php

/**
 * Start queue worker for Hostinger
 * Run this script to process queued emails
 */

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Artisan;

echo "=== STARTING QUEUE WORKER ===\n";
echo "Processing queued jobs...\n";
echo "Press Ctrl+C to stop\n\n";

try {
    // Start the queue worker
    Artisan::call('queue:work', [
        '--timeout' => 60,
        '--tries' => 3,
        '--max-time' => 3600
    ]);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
