<?php

/**
 * Fix queue issues on Hostinger
 */

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Artisan;

echo "=== FIXING QUEUE ISSUES ===\n\n";

// 1. Clear all failed jobs
echo "1. Clearing failed jobs...\n";
try {
    Artisan::call('queue:flush');
    echo "   ✅ Failed jobs cleared\n";
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// 2. Clear cache
echo "\n2. Clearing cache...\n";
try {
    Artisan::call('cache:clear');
    echo "   ✅ Cache cleared\n";
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// 3. Clear config cache
echo "\n3. Clearing config cache...\n";
try {
    Artisan::call('config:clear');
    echo "   ✅ Config cache cleared\n";
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// 4. Check queue status
echo "\n4. Checking queue status...\n";
try {
    $jobCount = \DB::table('jobs')->count();
    $failedJobCount = \DB::table('failed_jobs')->count();

    echo "   Jobs in queue: " . $jobCount . "\n";
    echo "   Failed jobs: " . $failedJobCount . "\n";

    if ($jobCount > 0) {
        echo "   ⚠️  There are jobs in the queue. Start queue worker to process them.\n";
    }

    if ($failedJobCount > 0) {
        echo "   ⚠️  There are failed jobs. Check logs for details.\n";
    }

} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// 5. Test email configuration
echo "\n5. Testing email configuration...\n";
try {
    $mailConfig = config('mail');
    echo "   MAIL_MAILER: " . $mailConfig['default'] . "\n";
    echo "   MAIL_HOST: " . $mailConfig['mailers']['smtp']['host'] . "\n";
    echo "   MAIL_FROM_ADDRESS: " . $mailConfig['from']['address'] . "\n";

    if ($mailConfig['default'] === 'smtp') {
        echo "   ✅ SMTP configuration looks correct\n";
    } else {
        echo "   ⚠️  Mail driver is not SMTP\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== QUEUE FIX COMPLETED ===\n";
echo "Next steps:\n";
echo "1. Run: php start_queue_worker.php (to process queued emails)\n";
echo "2. Or run: php artisan queue:work (in terminal)\n";
echo "3. Check your email inbox (including spam folder)\n";
