<?php

/**
 * Test email system on Hostinger deployment
 */

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\NotificationService;
use App\Models\Shipment;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

echo "=== HOSTINGER EMAIL SYSTEM TEST ===\n\n";

// Test 1: Check SMTP configuration
echo "1. Checking SMTP configuration...\n";
$mailConfig = config('mail');
echo "   MAIL_MAILER: " . $mailConfig['default'] . "\n";
echo "   MAIL_HOST: " . $mailConfig['mailers']['smtp']['host'] . "\n";
echo "   MAIL_PORT: " . $mailConfig['mailers']['smtp']['port'] . "\n";
echo "   MAIL_USERNAME: " . $mailConfig['mailers']['smtp']['username'] . "\n";
echo "   MAIL_FROM_ADDRESS: " . $mailConfig['from']['address'] . "\n";
echo "   MAIL_FROM_NAME: " . $mailConfig['from']['name'] . "\n\n";

// Test 2: Check queue configuration
echo "2. Checking queue configuration...\n";
$queueConfig = config('queue');
echo "   QUEUE_CONNECTION: " . $queueConfig['default'] . "\n";
echo "   QUEUE_DRIVER: " . $queueConfig['connections']['database']['driver'] . "\n\n";

// Test 3: Check if queue table exists
echo "3. Checking queue table...\n";
try {
    $jobCount = \DB::table('jobs')->count();
    echo "   Jobs in queue: " . $jobCount . "\n";

    $failedJobCount = \DB::table('failed_jobs')->count();
    echo "   Failed jobs: " . $failedJobCount . "\n";
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

// Test 4: Test email sending directly (without queue)
echo "\n4. Testing direct email sending...\n";
try {
    $testShipment = new Shipment([
        'id' => 999999,
        'tracking_number' => 'HOSTINGER-TEST-' . uniqid(),
        'sender_full_name' => 'Hostinger Test',
        'sender_email' => 'test@example.com',
        'created_at' => now(),
        'updated_at' => now()
    ]);

    $notificationService = new NotificationService();

    // Test admin notification
    $adminResult = $notificationService->sendAdminNewOrderNotification($testShipment, null);
    echo "   Admin notification: " . ($adminResult ? "SUCCESS" : "FAILED") . "\n";

    // Test customer confirmation
    $confirmationResult = $notificationService->sendShippingConfirmation($testShipment);
    echo "   Customer confirmation: " . ($confirmationResult ? "SUCCESS" : "FAILED") . "\n";

} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

// Test 5: Check storage permissions
echo "\n5. Checking storage permissions...\n";
$storagePath = storage_path('app/public/labels');
echo "   Storage path: " . $storagePath . "\n";
echo "   Directory exists: " . (is_dir($storagePath) ? "YES" : "NO") . "\n";
echo "   Directory writable: " . (is_writable($storagePath) ? "YES" : "NO") . "\n";

// Test 6: Check recent logs
echo "\n6. Checking recent email logs...\n";
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $logs = file_get_contents($logFile);
    $emailLogs = array_filter(explode("\n", $logs), function($line) {
        return strpos($line, 'email') !== false || strpos($line, 'mail') !== false;
    });
    $recentLogs = array_slice($emailLogs, -5);
    foreach ($recentLogs as $log) {
        echo "   " . $log . "\n";
    }
} else {
    echo "   No log file found\n";
}

echo "\n=== TEST COMPLETED ===\n";
echo "If emails are not being received, check:\n";
echo "1. Queue worker is running: php artisan queue:work\n";
echo "2. SMTP credentials are correct\n";
echo "3. Check spam folders\n";
echo "4. Check server logs for errors\n";
