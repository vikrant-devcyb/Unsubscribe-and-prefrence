<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShopifyController;
use App\Http\Controllers\ProxyController;
use App\Helpers\ShopStorage;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Artisan;

Route::get('/clear-cache', function () {
    Artisan::call('config:clear');
    Artisan::call('cache:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    
    return 'Config, cache, route, and view caches cleared!';
});

// OAuth start
Route::get('/shopify/install', [ShopifyController::class, 'install']);
Route::get('/shopify/callback', [ShopifyController::class, 'callback'])->name('shopify.callback');

// App proxy handler
// Route::get('/unsubscribe-preference-proxy-handler', [ProxyController::class, 'handle']);

Route::get('/', function (Request $request) {
    $shop = request()->get('shop'); // Get ?shop= param if passed
    
    if (!$shop) {
        return view('welcome');
        //return response("Missing shop parameter.", 400);
    }
    // $encrypted = ShopStorage::get($shop);
    // $accessToken = ShopStorage::decryptToken($encrypted);
    
    $accessToken = ShopStorage::get($shop);
    // $accessToken = env('SHOPIFY_ACCESS_TOKEN');
    if ($accessToken) {
        return view('shopify.dashboard', ['shop' => $shop]);
    } else {
        return view('shopify.not_installed', ['shop' => $shop]);
    }
})->name('shopify.home');

// Add to routes/web.php - handles requests from custom domain
Route::get('/apps/unsubscribe-preference/{path?}', function (Request $request, $path = '') {
    Log::info('Custom domain app proxy request', [
        'path' => $path,
        'params' => $request->all(),
        'headers' => $request->headers->all()
    ]);
    
    // Forward to your main proxy handler
    return app(ProxyController::class)->handle($request);
})->where('path', '.*');



// Add these routes to your routes/web.php

// Handle app proxy requests that might come from custom domain
Route::get('/apps/unsubscribe-preference', function (Request $request) {
    Log::info('Custom domain proxy request', [
        'url' => $request->fullUrl(),
        'params' => $request->all(),
        'headers' => $request->headers->all()
    ]);
    
    // Check if this looks like a proper Shopify app proxy request
    $hasShopParam = !empty($request->get('shop'));
    $hasSignature = !empty($request->get('signature'));
    
    if (!$hasShopParam || !$hasSignature) {
        return response()->json([
            'error' => 'Invalid app proxy request',
            'debug' => [
                'has_shop' => $hasShopParam,
                'has_signature' => $hasSignature,
                'url' => $request->fullUrl(),
                'params' => $request->all()
            ]
        ], 400);
    }
    
    // Forward to your main proxy handler
    return app(\App\Http\Controllers\ProxyController::class)->handle($request);
});

// Catch-all for app proxy paths
// Route::get('/apps/unsubscribe-preference/{path}', function (Request $request, $path) {
//     Log::info('Custom domain proxy with path', [
//         'path' => $path,
//         'url' => $request->fullUrl(),
//         'params' => $request->all()
//     ]);
    
//     return response()->json([
//         'status' => 'Custom domain app proxy path accessed',
//         'path' => $path,
//         'url' => $request->fullUrl(),
//         'params' => $request->all()
//     ], 200, [], JSON_PRETTY_PRINT);
// });

// Alternative: Handle the exact proxy path
Route::get('/unsubscribe-preference-proxy-handler', [\App\Http\Controllers\ProxyController::class, 'handle']);

// Debug route to test connectivity
Route::get('/proxy-debug', function (Request $request) {
    return response()->json([
        'message' => 'Proxy debug endpoint reached',
        'domain' => $request->getHost(),
        'url' => $request->fullUrl(),
        'params' => $request->all(),
        'shopify_user_agent' => str_contains($request->header('User-Agent', ''), 'Shopify'),
        'timestamp' => now()
    ], 200, [], JSON_PRETTY_PRINT);
});