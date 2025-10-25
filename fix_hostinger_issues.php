<?php

/**
 * Comprehensive fix for Hostinger deployment issues
 */

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

echo "=== HOSTINGER DEPLOYMENT FIX ===\n\n";

// 1. Fix storage and permissions
echo "1. Fixing storage and permissions...\n";
try {
    // Create labels directory
    $labelsDir = storage_path('app/public/labels');
    if (!is_dir($labelsDir)) {
        mkdir($labelsDir, 0755, true);
        echo "   ✅ Created labels directory\n";
    } else {
        echo "   ✅ Labels directory exists\n";
    }

    // Create storage link
    $publicLink = public_path('storage');
    if (!is_link($publicLink) && !is_dir($publicLink)) {
        Artisan::call('storage:link');
        echo "   ✅ Created storage link\n";
    } else {
        echo "   ✅ Storage link exists\n";
    }

    // Set permissions
    chmod($labelsDir, 0755);
    echo "   ✅ Set directory permissions\n";

} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// 2. Clear all caches
echo "\n2. Clearing caches...\n";
try {
    Artisan::call('cache:clear');
    echo "   ✅ Application cache cleared\n";

    Artisan::call('config:clear');
    echo "   ✅ Config cache cleared\n";

    Artisan::call('route:clear');
    echo "   ✅ Route cache cleared\n";

    Artisan::call('view:clear');
    echo "   ✅ View cache cleared\n";

} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// 3. Fix queue issues
echo "\n3. Fixing queue issues...\n";
try {
    // Clear failed jobs
    Artisan::call('queue:flush');
    echo "   ✅ Cleared failed jobs\n";

    // Check queue status
    $jobCount = DB::table('jobs')->count();
    $failedJobCount = DB::table('failed_jobs')->count();

    echo "   Jobs in queue: " . $jobCount . "\n";
    echo "   Failed jobs: " . $failedJobCount . "\n";

} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// 4. Test email configuration
echo "\n4. Testing email configuration...\n";
try {
    $mailConfig = config('mail');
    echo "   MAIL_MAILER: " . $mailConfig['default'] . "\n";
    echo "   MAIL_HOST: " . $mailConfig['mailers']['smtp']['host'] . "\n";
    echo "   MAIL_PORT: " . $mailConfig['mailers']['smtp']['port'] . "\n";
    echo "   MAIL_FROM_ADDRESS: " . $mailConfig['from']['address'] . "\n";

    if ($mailConfig['default'] === 'smtp') {
        echo "   ✅ SMTP configuration looks correct\n";
    } else {
        echo "   ⚠️  Mail driver is not SMTP\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// 5. Test label generation
echo "\n5. Testing label generation...\n";
try {
    $shipment = \App\Models\Shipment::orderBy('id', 'desc')->first();
    if ($shipment) {
        echo "   Latest shipment ID: " . $shipment->id . "\n";
        echo "   Tracking number: " . $shipment->tracking_number . "\n";
        echo "   Status: " . $shipment->status . "\n";

        $labelFile = storage_path("app/public/labels/{$shipment->id}.pdf");
        echo "   Label file: " . $labelFile . "\n";
        echo "   File exists: " . (file_exists($labelFile) ? "YES" : "NO") . "\n";

        if (file_exists($labelFile)) {
            echo "   File size: " . filesize($labelFile) . " bytes\n";
            echo "   File readable: " . (is_readable($labelFile) ? "YES" : "NO") . "\n";
        }
    } else {
        echo "   No shipments found\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// 6. Test email sending
echo "\n6. Testing email sending...\n";
try {
    $testShipment = new \App\Models\Shipment([
        'id' => 999999,
        'tracking_number' => 'HOSTINGER-TEST-' . uniqid(),
        'sender_full_name' => 'Hostinger Test',
        'sender_email' => 'test@example.com',
        'created_at' => now(),
        'updated_at' => now()
    ]);

    $notificationService = new \App\Services\NotificationService();

    // Test admin notification (without queue for testing)
    $adminResult = $notificationService->sendAdminNewOrderNotification($testShipment, null);
    echo "   Admin notification: " . ($adminResult ? "SUCCESS" : "FAILED") . "\n";

} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// 7. Check recent logs
echo "\n7. Checking recent logs...\n";
try {
    $logFile = storage_path('logs/laravel.log');
    if (file_exists($logFile)) {
        $logs = file_get_contents($logFile);
        $emailLogs = array_filter(explode("\n", $logs), function($line) {
            return strpos($line, 'email') !== false || strpos($line, 'mail') !== false;
        });
        $recentLogs = array_slice($emailLogs, -3);
        foreach ($recentLogs as $log) {
            echo "   " . $log . "\n";
        }
    } else {
        echo "   No log file found\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== FIX COMPLETED ===\n";
echo "Next steps:\n";
echo "1. Start queue worker: php artisan queue:work\n";
echo "2. Or run: php start_queue_worker.php\n";
echo "3. Test a new shipment to see if emails are sent\n";
echo "4. Check your email inbox (including spam folder)\n";
echo "5. Check if labels are now visible on the success page\n";
