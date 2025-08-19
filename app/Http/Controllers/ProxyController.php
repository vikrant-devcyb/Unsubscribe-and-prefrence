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

    private function validateSignature($params, $signature)
    {
        if (!$signature) {
            Log::warning('No signature provided');
            return false;
        }

        $shared_secret = env('SHOPIFY_API_SECRET_KEY');
        
        Log::info('Using secret key', ['secret_length' => strlen($shared_secret), 'secret_prefix' => substr($shared_secret, 0, 8)]);
        
        // Remove signature and hmac from params (same as your validateHmac function)
        unset($params['signature'], $params['hmac']);
        
        // Sort parameters by key (same as your validateHmac function)
        ksort($params);
        
        Log::info('Sorted parameters', $params);
        
        // Use http_build_query like your working validateHmac function
        $queryString = http_build_query($params);
        
        Log::info('Query string for signature validation', ['query_string' => $queryString]);
        
        // Compute HMAC (same as your validateHmac function)
        $computed_hmac = hash_hmac('sha256', $queryString, $shared_secret);
        
        Log::info('Signature validation', [
            'provided_signature' => $signature,
            'computed_hmac' => $computed_hmac,
            'query_string' => $queryString
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
            return response()->json(['message' => 'Customer is already unsubscribed']);
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