<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Helpers\ShopStorage;

class ProxyController extends Controller
{
    /**
     * Shopify Proxy Redirect API call on it.
     * Proxy Handle
     * Validate Singnature || Security Purpose || Valid Call
    */
    public function handle(Request $request)
    {
        $action = $request->query('action');
        Log::info('Proxy hit', $request->all());

        if (!$this->validateSignature($request->all(), $request->get('signature'))) {
            Log::error('Invalid app proxy signature', $request->all());
            return response()->json(['error' => 'Invalid app proxy signature'], 403);
        }

        $this->unsubscribeCustomer($request);
    }

    /**
     * Validate signatures from Shopify
     * Returns: boolean (true if valid, false if invalid)
    */
    private function validateSignature($params, $signature)
    {
        if (!$signature){
            Log::warning('No signature provided');
            return false;

        }
        $shared_secret = env('SHOPIFY_API_KEY');
        $params = request()->all();
        if (!$params['logged_in_customer_id']){
            $params['logged_in_customer_id'] = $params['logged_in_customer_id'] ?? "";

        }
        $params = array_diff_key($params, array('signature' => ''));
        ksort($params);
        $params = str_replace("%2F","/",http_build_query($params));
        $params = str_replace("&","",$params);
        $params = str_replace("%2C", ",", $params);
        $computed_hmac = hash_hmac('sha256', $params, $shared_secret);
        return hash_equals($signature, $computed_hmac);
    }

    /**
     * Unsubscribe Customer by Using Admin API
     * Handle cases || Search Customer || Check Valid Customer
     * Handle Response
    */
    public function unsubscribeCustomer(Request $request)
    {
        $email = $request->query('email');
        $shopDomain = $request->query('shop');

        if (!$email || !$shopDomain) {
            return response()->json(['error' => 'Missing email or shop parameter'], 400);
        }

        // $shop = Shop::where('shopify_domain', $shopDomain)->first();
        // if (!$shop) {
        //     return response()->json(['error' => 'Shop not found'], 404);
        // }

        $encrypted = ShopStorage::get($shopDomain);
        $accessToken = ShopStorage::decryptToken($encrypted);
        if (!$shopDomain) {
            return response()->json(['error' => 'Shop not found'], 404);
        }

        $accessToken = $shop->access_token;
        // Search customer by email
        $searchResponse = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
        ])->get("https://{$shopDomain}/admin/api/2024-04/customers/search.json", [
            'query' => $email
        ]);

        if ($searchResponse->failed() || empty($searchResponse['customers'])) {
            return response()->json(['error' => 'Customer not found'], 404);
        }

        $customer = $searchResponse['customers'][0];
        $customerId = $customer['id'];

        //Check if already unsubscribed
        $emailConsent = $customer['email_marketing_consent']['state'] ?? null;

        if ($emailConsent === 'unsubscribed') {
            return response()->json(['message' => 'Customer is already unsubscribed']);
        }

        // Update customer to unsubscribe
        $updateResponse = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
            'Content-Type' => 'application/json'
        ])->put("https://{$shopDomain}/admin/api/2025-04/customers/{$customerId}.json", [
            'customer' => [
                'id' => $customerId,
                'email_marketing_consent' => [
                    'state' => 'unsubscribed'
                ]
            ]
        ]);

        if ($updateResponse->successful()) {
            return response()->json(['message' => 'Customer unsubscribed successfully']);
        } else {
            return response()->json(['error' => 'Failed to unsubscribe customer'], 500);
        }
    }

}

