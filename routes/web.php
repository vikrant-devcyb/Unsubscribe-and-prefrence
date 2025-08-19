<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShopifyController;
use App\Http\Controllers\ProxyController;
use App\Helpers\ShopStorage;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Artisan;

Route::get('/clear-cache', function () {
    Artisan::call('config:clear');
    Artisan::call('config:cache');
    Artisan::call('cache:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    
    return 'Config, cache, route, and view caches cleared!';
});

// OAuth start
Route::get('/shopify/install', [ShopifyController::class, 'install']);
Route::get('/shopify/callback', [ShopifyController::class, 'callback'])->name('shopify.callback');

// App proxy handler
Route::get('/unsubscribe-preference-proxy-handler', [ProxyController::class, 'handle']);

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



// Test route that doesn't require signature validation
Route::get('/proxy-test', function (Request $request) {
    return response()->json([
        'status' => 'App proxy is reachable',
        'timestamp' => now(),
        'all_params' => $request->all(),
        'query_string' => $request->getQueryString(),
        'shop' => $request->get('shop'),
        'signature_provided' => !empty($request->get('signature')),
        'headers' => $request->headers->all()
    ], 200, [], JSON_PRETTY_PRINT);
});

// Test signature validation specifically
Route::get('/test-signature', function (Request $request) {
    $signature = $request->get('signature');
    $params = $request->query();
    unset($params['signature']);
    unset($params['hmac']);
    
    if (!isset($params['logged_in_customer_id'])) {
        $params['logged_in_customer_id'] = '';
    }
    
    ksort($params);
    $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    $computed = hash_hmac('sha256', $queryString, env('SHOPIFY_API_SECRET_KEY'));
    
    return response()->json([
        'provided_signature' => $signature,
        'computed_signature' => $computed,
        'query_string' => $queryString,
        'params' => $params,
        'match' => hash_equals($computed, $signature ?? ''),
        'secret_configured' => !empty(env('SHOPIFY_API_SECRET_KEY')),
        'secret_length' => strlen(env('SHOPIFY_API_SECRET_KEY') ?? ''),
        'request_came_from_shopify' => str_contains($request->header('User-Agent', ''), 'Shopify') || !empty($request->get('shop'))
    ], 200, [], JSON_PRETTY_PRINT);
});