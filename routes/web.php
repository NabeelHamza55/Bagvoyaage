<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShipmentController;

// Homepage with origin/destination form
Route::get('/', [ShipmentController::class, 'index'])->name('home');

// Detailed shipment form
Route::get('/shipment/create', [ShipmentController::class, 'showCreateForm'])->name('shipment.form');
Route::post('/shipment/create', [ShipmentController::class, 'create'])->name('shipment.create');

// Process detailed shipment form and get FedEx rates
Route::post('/shipment/quote', [ShipmentController::class, 'getQuote'])->name('shipment.quote');

// Checkout page
Route::get('/shipment/{shipment}/checkout', [ShipmentController::class, 'checkout'])->name('shipment.checkout');

// Payment processing
Route::post('/shipment/{shipment}/payment', [ShipmentController::class, 'processPayment'])->name('shipment.payment');

// Payment success/failure callbacks
Route::get('/payment/success', [ShipmentController::class, 'paymentSuccess'])->name('payment.success');
Route::get('/payment/cancel', [ShipmentController::class, 'paymentCancel'])->name('payment.cancel');

// Shipment success page with all management options
Route::get('/shipment/{shipment}/success', [ShipmentController::class, 'success'])->name('shipment.success');

// Pickup scheduling
Route::get('/shipment/{shipment}/pickup', [ShipmentController::class, 'showPickupForm'])->name('shipment.pickup.form');
Route::post('/shipment/{shipment}/pickup', [ShipmentController::class, 'schedulePickup'])->name('shipment.pickup.schedule');

// Label download and view
Route::get('/shipment/{shipment}/label', [ShipmentController::class, 'downloadLabel'])->name('shipment.label.download');
Route::get('/shipment/{shipment}/label/view', [ShipmentController::class, 'viewLabel'])->name('shipment.label.view');

// Shipment tracking
Route::get('/shipment/{shipment}/tracking', [ShipmentController::class, 'trackShipment'])->name('shipment.tracking');
Route::get('/track/{tracking_number}', [ShipmentController::class, 'track'])->name('shipment.track');

// Webhook routes
Route::post('/webhook/paypal', [App\Http\Controllers\WebhookController::class, 'paypal'])->name('webhook.paypal');
Route::post('/webhook/test', [App\Http\Controllers\WebhookController::class, 'test'])->name('webhook.test');
Route::get('/webhook/status', [App\Http\Controllers\WebhookController::class, 'status'])->name('webhook.status');

// Utility routes
Route::get('/cities/{state}', [ShipmentController::class, 'getCities'])->name('cities.by.state');

// Development route to rewrite all migrations
Route::get('/migrations/rewrite', function () {
    try {
        // Drop all tables
        \Illuminate\Support\Facades\Artisan::call('migrate:fresh', [
            '--force' => true
        ]);

        // Run all migrations
        \Illuminate\Support\Facades\Artisan::call('migrate', [
            '--force' => true
        ]);

        // Run seeders if they exist
        try {
            \Illuminate\Support\Facades\Artisan::call('db:seed', [
                '--force' => true
            ]);
        } catch (\Exception $e) {
            // Seeders might not exist, that's okay
        }

        return response()->json([
            'success' => true,
            'message' => 'All migrations have been rewritten successfully',
            'timestamp' => now()->toDateTimeString()
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error rewriting migrations: ' . $e->getMessage(),
            'timestamp' => now()->toDateTimeString()
        ], 500);
    }
})->name('dev.rewrite.migrations');

// Cache optimization route
Route::get('/cache/optimize', function () {
    try {
        $results = [];

        // Note: Cache optimization requires Artisan commands which use exec()
        // On Hostinger, these functions are disabled, so we'll skip optimization
        $results[] = 'Cache optimization skipped - exec() function disabled on this server';
        $results[] = 'Manual optimization not available without exec() function';
        $results[] = 'Application is running without optimization (this is normal for shared hosting)';

        // Try to manually clear all caches instead
        $clearedCount = 0;

        // Clear application cache
        $cacheDir = storage_path('framework/cache/data');
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                    $clearedCount++;
                }
            }
            $results[] = "Cleared application cache files";
        }

        // Clear config cache
        $configCache = bootstrap_path('cache/config.php');
        if (file_exists($configCache)) {
            unlink($configCache);
            $clearedCount++;
            $results[] = "Cleared config cache";
        }

        // Clear route cache
        $routeCache = bootstrap_path('cache/routes-v7.php');
        if (file_exists($routeCache)) {
            unlink($routeCache);
            $clearedCount++;
            $results[] = "Cleared route cache";
        }

        // Clear view cache
        $viewCacheDir = storage_path('framework/views');
        if (is_dir($viewCacheDir)) {
            $viewFiles = glob($viewCacheDir . '/*');
            foreach ($viewFiles as $file) {
                if (is_file($file)) {
                    unlink($file);
                    $clearedCount++;
                }
            }
            $results[] = "Cleared view cache";
        }

        // Clear event cache
        $eventCache = bootstrap_path('cache/events.php');
        if (file_exists($eventCache)) {
            unlink($eventCache);
            $clearedCount++;
            $results[] = "Cleared event cache";
        }

        $results[] = "Total cleared: {$clearedCount} cache files";

        $results[] = 'Cache optimization completed (manual mode)';

        return response()->json([
            'success' => true,
            'message' => 'Cache optimization completed (manual mode)',
            'results' => $results,
            'timestamp' => now()->toDateTimeString()
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error during cache optimization: ' . $e->getMessage(),
            'timestamp' => now()->toDateTimeString()
        ], 500);
    }
})->name('admin.cache.optimize');

// Command-based routes for Laravel operations
Route::get('/cmd/cache/clear', function () {
    try {
        $results = [];

        // Clear all caches manually
        $cacheDir = storage_path('framework/cache/data');
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) unlink($file);
            }
            $results[] = 'Application cache cleared';
        }

        $configCache = bootstrap_path('cache/config.php');
        if (file_exists($configCache)) {
            unlink($configCache);
            $results[] = 'Config cache cleared';
        }

        $routeCache = bootstrap_path('cache/routes-v7.php');
        if (file_exists($routeCache)) {
            unlink($routeCache);
            $results[] = 'Route cache cleared';
        }

        $viewCacheDir = storage_path('framework/views');
        if (is_dir($viewCacheDir)) {
            $files = glob($viewCacheDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) unlink($file);
            }
            $results[] = 'View cache cleared';
        }

        return response()->json([
            'success' => true,
            'message' => 'Cache cleared successfully',
            'results' => $results,
            'timestamp' => now()->toDateTimeString()
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error clearing cache: ' . $e->getMessage(),
            'timestamp' => now()->toDateTimeString()
        ], 500);
    }
})->name('cmd.cache.clear');

Route::get('/cmd/config/clear', function () {
    try {
        $results = [];

        $configCache = bootstrap_path('cache/config.php');
        if (file_exists($configCache)) {
            unlink($configCache);
            $results[] = 'Config cache cleared successfully';
        } else {
            $results[] = 'No config cache found';
        }

        return response()->json([
            'success' => true,
            'message' => 'Config cache cleared',
            'results' => $results,
            'timestamp' => now()->toDateTimeString()
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error clearing config cache: ' . $e->getMessage(),
            'timestamp' => now()->toDateTimeString()
        ], 500);
    }
})->name('cmd.config.clear');

Route::get('/cmd/route/clear', function () {
    try {
        $results = [];

        $routeCache = bootstrap_path('cache/routes-v7.php');
        if (file_exists($routeCache)) {
            unlink($routeCache);
            $results[] = 'Route cache cleared successfully';
        } else {
            $results[] = 'No route cache found';
        }

        return response()->json([
            'success' => true,
            'message' => 'Route cache cleared',
            'results' => $results,
            'timestamp' => now()->toDateTimeString()
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error clearing route cache: ' . $e->getMessage(),
            'timestamp' => now()->toDateTimeString()
        ], 500);
    }
})->name('cmd.route.clear');

Route::get('/cmd/view/clear', function () {
    try {
        $results = [];

        $viewCacheDir = storage_path('framework/views');
        if (is_dir($viewCacheDir)) {
            $files = glob($viewCacheDir . '/*');
            $clearedCount = 0;
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                    $clearedCount++;
                }
            }
            $results[] = "Cleared {$clearedCount} view cache files";
        } else {
            $results[] = 'No view cache directory found';
        }

        return response()->json([
            'success' => true,
            'message' => 'View cache cleared',
            'results' => $results,
            'timestamp' => now()->toDateTimeString()
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error clearing view cache: ' . $e->getMessage(),
            'timestamp' => now()->toDateTimeString()
        ], 500);
    }
})->name('cmd.view.clear');

Route::get('/cmd/queue/clear', function () {
    try {
        $results = [];

        // Clear queued jobs
        $deletedJobs = \DB::table('jobs')->delete();
        $results[] = "Cleared {$deletedJobs} queued jobs";

        // Clear failed jobs
        $deletedFailed = \DB::table('failed_jobs')->delete();
        $results[] = "Cleared {$deletedFailed} failed jobs";

        return response()->json([
            'success' => true,
            'message' => 'Queue cleared successfully',
            'results' => $results,
            'timestamp' => now()->toDateTimeString()
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error clearing queue: ' . $e->getMessage(),
            'timestamp' => now()->toDateTimeString()
        ], 500);
    }
})->name('cmd.queue.clear');

