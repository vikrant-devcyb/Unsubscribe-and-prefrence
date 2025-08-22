<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShopifyController;
use App\Http\Controllers\ProxyController;
use App\Helpers\ShopStorage;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Artisan;

Route::delete('/shop/{shop}', function ($shopDomain, Request $request) {
    $deleted = ShopStorage::delete($shopDomain); // Changed from Shop model

    if ($deleted) {
        return redirect('/')->with('status', 'Shop data deleted successfully!');
    }

    return redirect('/')->with('error', 'Shop not found.');
})->name('shop.delete');

Route::get('/clear-cache', function () {
    Artisan::call('config:clear');

    return 'Config, cache, route, and view caches cleared!';
});

// Debug route to check JSON file status
Route::get('/debug-storage', function () {
    if (!app()->environment('production')) {
        $info = ShopStorage::getFileInfo();
        return response()->json($info, 200, [], JSON_PRETTY_PRINT);
    }
    return abort(404);
});

// OAuth start
Route::get('/shopify/install', [ShopifyController::class, 'install']);
Route::get('/shopify/callback', [ShopifyController::class, 'callback'])->name('shopify.callback');

// App proxy handler
Route::get('/unsubscribe-preference-proxy-handler', [ProxyController::class, 'handle']);

Route::get('/', function (Request $request) {
    $shop = request()->get('shop');
    if (!$shop) {
        return view('welcome');
    }

    try {
        $shopModel = ShopStorage::getShop($shop);
        
        Log::info('Dashboard check', [
            'shop' => $shop,
            'shop_model_exists' => $shopModel ? 'yes' : 'no',
            'has_token' => $shopModel && $shopModel->access_token ? 'yes' : 'no'
        ]);
        
        if ($shopModel && $shopModel->access_token) {
            return view('shopify.dashboard', [
                'shop' => $shop,
                'installed_at' => $shopModel->installed_at
            ]);
        } else {
            Log::warning('Redirecting to install - no valid token found', ['shop' => $shop]);
            return view('shopify.not_installed', ['shop' => $shop]);
        }
    } catch (\Exception $e) {
        Log::error('Dashboard exception', ['shop' => $shop, 'error' => $e->getMessage()]);
        return view('shopify.not_installed', ['shop' => $shop]);
    }
});


Route::get('/debug-env', function () {
    return response()->json([
        'shopify_api_key' => env('SHOPIFY_API_KEY') ? 'set' : 'missing',
        'shopify_api_secret' => env('SHOPIFY_API_SECRET_KEY') ? 'set' : 'missing',
        'app_url' => env('APP_URL'),
        'file_storage_info' => ShopStorage::getFileInfo(),
    ], 200, [], JSON_PRETTY_PRINT);
});