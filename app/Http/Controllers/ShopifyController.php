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
            return redirect()->back()->with('error', 'Shop parameter is required');
        }

        $scopes = 'read_products,read_inventory,read_locations,read_script_tags,write_script_tags,read_customers,write_customers';
        $redirectUri = urlencode(env('APP_URL') . '/shopify/callback');
        $apiKey = env('SHOPIFY_API_KEY');
        $installUrl = "https://{$shop}/admin/oauth/authorize?client_id={$apiKey}&scope={$scopes}&redirect_uri={$redirectUri}&state=123&grant_options[]=per-user";
        
        return redirect($installUrl);
    }

    public function callback(Request $request)
    {
        if (!$this->validateHmac($request->all(), $request->get('hmac'))) {
            abort(403, 'Invalid HMAC');
        }

        $shop = $request->get('shop');
        $code = $request->get('code');
        $apiSecret = env('SHOPIFY_API_SECRET_KEY');
        $apiKey = env('SHOPIFY_API_KEY');

        if (!$shop || !$code) {
            abort(400, 'Missing required parameters');
        }

        try {
            // Exchange code for access token
            $response = Http::post("https://{$shop}/admin/oauth/access_token", [
                'client_id' => $apiKey,
                'client_secret' => $apiSecret,
                'code' => $code,
            ]);

            if ($response->failed() || !isset($response['access_token'])) {
                abort(500, 'Failed to authenticate with Shopify');
            }

            $accessToken = $response['access_token'];
            
            // Store in JSON file using ShopStorage
            $stored = ShopStorage::set($shop, $accessToken);
            if (!$stored) {
                abort(500, 'Failed to store authentication data');
            }

            $retrievedToken = ShopStorage::get($shop);
            // Set session data
            session(['shop' => $shop, 'access_token' => $accessToken]);

            // Inject ScriptTag
            InjectScriptTagToShop::dispatch($shop, $accessToken);

            return view('shopify.installed', [
                'shop' => $shop,
                'unsubscribedCount' => 0,
                'lastUnsubscribedAt' => null,
            ]);

        } catch (\Exception $e) {
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
            //
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
        }

        return response()->json(['status' => 'success']);
    }
}