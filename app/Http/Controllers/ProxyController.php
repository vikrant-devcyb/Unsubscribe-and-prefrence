<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Helpers\ShopStorage;

class ProxyController extends Controller
{
    public function handle(Request $request)
    {
        $action = $request->query('action');
        Log::info('Proxy hit', $request->all());

        if (!$this->validateSignature($request->all(), $request->get('signature'))) {
            Log::error('Invalid app proxy signature', $request->all());
            return response()->json(['error' => 'Invalid app proxy signature'], 403);
        }
        
        return $this->unsubscribeCustomer($request);
    }

    /*private function validateSignature($params, $signature)
    {
        if (!$signature) {
            Log::warning('No signature provided');
            return false;
        }

        $shared_secret = env('SHOPIFY_API_SECRET_KEY');
        $params = request()->all();
        
        if (!isset($params['logged_in_customer_id'])) {
            $params['logged_in_customer_id'] = "";
        }

        $params = array_diff_key($params, array('signature' => ''));
        ksort($params);
        $params = str_replace("%2F", "/", http_build_query($params));
        $params = str_replace("&", "", $params);
        $params = str_replace("%2C", ",", $params);
        $computed_hmac = hash_hmac('sha256', $params, $shared_secret);
        
        return hash_equals($signature, $computed_hmac);
    }*/

    private function validateSignature($params, $signature)
    {
        if (!$signature) {
            Log::warning('No signature provided');
            return false;
        }

        $shared_secret = env('SHOPIFY_API_SECRET_KEY');

        unset($params['signature']);
        ksort($params);

        // DO NOT urldecode here
        $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        $computed_hmac = hash_hmac('sha256', $queryString, $shared_secret);

        Log::info('Computed HMAC', [
            'queryString' => $queryString,
            'computed' => $computed_hmac,
            'signature' => $signature,
            'shared_secret' => $shared_secret
        ]);

        return hash_equals($signature, $computed_hmac);
    }

    public function unsubscribeCustomer(Request $request)
    {
        $email = $request->query('email');
        $shopDomain = $request->query('shop');

        if (!$email || !$shopDomain) {
            return response()->json(['error' => 'Missing email or shop parameter'], 400);
        }

        // Get access token from SQLite database
        $accessToken = ShopStorage::get($shopDomain);
        
        if (!$accessToken) {
            Log::error("Access token not found for shop: {$shopDomain}");
            return response()->json(['error' => 'Shop not found or not authenticated'], 404);
        }

        // Search for customer
        $searchResponse = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
        ])->get("https://{$shopDomain}/admin/api/2024-04/customers/search.json", [
            'query' => $email
        ]);

        if ($searchResponse->failed() || empty($searchResponse['customers'])) {
            Log::error("Customer not found: {$email} in shop: {$shopDomain}");
            return response()->json(['error' => 'Customer not found'], 404);
        }

        $customer = $searchResponse['customers'][0];
        $customerId = $customer['id'];
        $emailConsent = $customer['email_marketing_consent']['state'] ?? null;

        if ($emailConsent === 'unsubscribed') {
            return response()->json(['message' => 'Your request has been received. Your email address would be removed from our marketing system within 24 hours']);
        }

        // Update customer to unsubscribed
        $updateResponse = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
            'Content-Type' => 'application/json'
        ])->put("https://{$shopDomain}/admin/api/2024-04/customers/{$customerId}.json", [
            'customer' => [
                'id' => $customerId,
                'email_marketing_consent' => [
                    'state' => 'unsubscribed'
                ]
            ]
        ]);

        if ($updateResponse->successful()) {
            Log::info("Customer unsubscribed successfully: {$email} from shop: {$shopDomain}");
            return response()->json(['message' => 'Customer unsubscribed successfully']);
        } else {
            Log::error("Failed to unsubscribe customer: {$email} from shop: {$shopDomain}", [
                'response' => $updateResponse->json()
            ]);
            return response()->json(['error' => 'Failed to unsubscribe customer'], 500);
        }
    }
}