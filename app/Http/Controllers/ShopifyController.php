<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Jobs\InjectScriptTagToShop;
use App\Helpers\ShopStorage;

class ShopifyController extends Controller
{
    public function install(Request $request)
    {
        $shop = $request->get('shop');
        
        if (!$shop) {
            Log::error('Shop parameter missing in install request');
            return redirect()->back()->with('error', 'Shop parameter is required');
        }

        $scopes = 'read_products,read_inventory,read_locations,read_script_tags,write_script_tags,read_customers,write_customers';
        $redirectUri = urlencode(env('APP_URL') . '/shopify/callback');
        $apiKey = env('SHOPIFY_API_KEY');
        $installUrl = "https://{$shop}/admin/oauth/authorize?client_id={$apiKey}&scope={$scopes}&redirect_uri={$redirectUri}&state=123&grant_options[]=per-user";
        
        Log::info("Redirecting to Shopify install URL for shop: {$shop}");
        return redirect($installUrl);
    }

    // public function callback(Request $request)
    // {
    //     Log::info('Shopify callback received', $request->all());

    //     if (!$this->validateHmac($request->all(), $request->get('hmac'))) {
    //         Log::error('Invalid HMAC in callback', $request->all());
    //         abort(403, 'Invalid HMAC');
    //     }

    //     $shop = $request->get('shop');
    //     $code = $request->get('code');
    //     $apiSecret = env('SHOPIFY_API_SECRET_KEY');
    //     $apiKey = env('SHOPIFY_API_KEY');

    //     if (!$shop || !$code) {
    //         Log::error('Missing shop or code in callback', $request->all());
    //         abort(400, 'Missing required parameters');
    //     }

    //     try {
    //         // Exchange code for access token
    //         $response = Http::post("https://{$shop}/admin/oauth/access_token", [
    //             'client_id' => $apiKey,
    //             'client_secret' => $apiSecret,
    //             'code' => $code,
    //         ]);

    //         if ($response->failed() || !isset($response['access_token'])) {
    //             Log::error('Failed to get access token from Shopify', [
    //                 'shop' => $shop,
    //                 'response' => $response->json()
    //             ]);
    //             abort(500, 'Failed to authenticate with Shopify');
    //         }

    //         $accessToken = $response['access_token'];
            
    //         // Store in JSON file using ShopStorage
    //         $stored = ShopStorage::set($shop, $accessToken);
            
    //         if (!$stored) {
    //             Log::error('Failed to store access token in database', ['shop' => $shop]);
    //             abort(500, 'Failed to store authentication data');
    //         }

    //         // Set session data
    //         session(['shop' => $shop, 'access_token' => $accessToken]);

    //         // Inject ScriptTag with APP_URL
    //         InjectScriptTagToShop::dispatch($shop, $accessToken);

    //         Log::info("Shop {$shop} installed successfully");

    //         // Get some stats for the view (you can customize this)
    //         $unsubscribedCount = $this->getUnsubscribedCount($shop, $accessToken);
    //         $lastDate = '05-07-2025'; // You can implement this logic

    //         return view('shopify.installed', [
    //             'shop' => $shop,
    //             'unsubscribedCount' => $unsubscribedCount,
    //             'lastUnsubscribedAt' => $lastDate ? \Carbon\Carbon::parse($lastDate)->format('d M Y, h:i A') : null,
    //         ]);

    //     } catch (\Exception $e) {
    //         Log::error('Exception during Shopify callback', [
    //             'shop' => $shop,
    //             'error' => $e->getMessage()
    //         ]);
    //         abort(500, 'Installation failed');
    //     }
    // }

    public function callback(Request $request)
    {
        Log::info('=== CALLBACK START ===', $request->all());

        if (!$this->validateHmac($request->all(), $request->get('hmac'))) {
            Log::error('Invalid HMAC in callback', $request->all());
            abort(403, 'Invalid HMAC');
        }

        $shop = $request->get('shop');
        $code = $request->get('code');
        $apiSecret = env('SHOPIFY_API_SECRET_KEY');
        $apiKey = env('SHOPIFY_API_KEY');

        Log::info('Environment check', [
            'shop' => $shop,
            'code' => $code ? 'present' : 'missing',
            'api_key' => $apiKey ? 'present' : 'missing',
            'api_secret' => $apiSecret ? 'present' : 'missing'
        ]);

        if (!$shop || !$code) {
            Log::error('Missing shop or code in callback', $request->all());
            abort(400, 'Missing required parameters');
        }

        try {
            // Exchange code for access token
            $response = Http::post("https://{$shop}/admin/oauth/access_token", [
                'client_id' => $apiKey,
                'client_secret' => $apiSecret,
                'code' => $code,
            ]);

            Log::info('Shopify token exchange response', [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'has_access_token' => isset($response['access_token']),
                'response_keys' => array_keys($response->json() ?? [])
            ]);

            if ($response->failed() || !isset($response['access_token'])) {
                Log::error('Failed to get access token from Shopify', [
                    'shop' => $shop,
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
                abort(500, 'Failed to authenticate with Shopify');
            }

            $accessToken = $response['access_token'];
            Log::info('Access token received', ['token_length' => strlen($accessToken)]);
            
            // Store in JSON file using ShopStorage
            Log::info('Attempting to store token...');
            $stored = ShopStorage::set($shop, $accessToken);
            
            Log::info('Storage result', [
                'stored' => $stored,
                'file_info' => ShopStorage::getFileInfo()
            ]);
            
            if (!$stored) {
                Log::error('Failed to store access token in JSON file', ['shop' => $shop]);
                abort(500, 'Failed to store authentication data');
            }

            // Test retrieval immediately
            $retrievedToken = ShopStorage::get($shop);
            Log::info('Token retrieval test', [
                'retrieved' => $retrievedToken ? 'success' : 'failed',
                'matches' => $retrievedToken === $accessToken
            ]);

            // Set session data
            session(['shop' => $shop, 'access_token' => $accessToken]);

            // Inject ScriptTag
            InjectScriptTagToShop::dispatch($shop, $accessToken);

            Log::info("=== CALLBACK SUCCESS === Shop {$shop} installed successfully");

            return view('shopify.installed', [
                'shop' => $shop,
                'unsubscribedCount' => 0,
                'lastUnsubscribedAt' => null,
            ]);

        } catch (\Exception $e) {
            Log::error('=== CALLBACK EXCEPTION ===', [
                'shop' => $shop,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            abort(500, 'Installation failed: ' . $e->getMessage());
        }
    }

    private function validateHmac($params, $hmac)
    {
        if (!$hmac) {
            return false;
        }

        unset($params['hmac'], $params['signature']);
        ksort($params);
        $computedHmac = hash_hmac('sha256', http_build_query($params), env('SHOPIFY_API_SECRET_KEY'));
        
        return hash_equals($computedHmac, $hmac);
    }

    /**
     * Get unsubscribed customers count (optional method for dashboard)
     */
    private function getUnsubscribedCount($shop, $accessToken)
    {
        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
            ])->get("https://{$shop}/admin/api/2024-04/customers.json", [
                'email_marketing_consent.state' => 'unsubscribed',
                'limit' => 1 // Just to get count
            ]);

            if ($response->successful()) {
                return count($response['customers']) ?? 0;
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get unsubscribed count', ['error' => $e->getMessage()]);
        }

        return 0;
    }

    /**
     * Uninstall webhook handler (optional)
     */
    public function uninstall(Request $request)
    {
        $shop = $request->header('X-Shopify-Shop-Domain');
        
        if ($shop) {
            ShopStorage::delete($shop);
            Log::info("Shop {$shop} uninstalled and removed from database");
        }

        return response()->json(['status' => 'success']);
    }
}