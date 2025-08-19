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
        Log::info('Proxy hit', [
            'all_params' => $request->all(),
            'query_string' => $request->getQueryString(),
            'method' => $request->method(),
            'url' => $request->fullUrl()
        ]);

        if (!$this->validateSignature($request)) {
            Log::error('Invalid app proxy signature', [
                'params' => $request->all(),
                'query_string' => $request->getQueryString(),
                'signature' => $request->get('signature')
            ]);
            return response()->json(['error' => 'Invalid app proxy signature'], 403);
        }
        
        return $this->unsubscribeCustomer($request);
    }

    /**
     * Validate Shopify App Proxy signature
     * Reference: https://shopify.dev/docs/apps/build/authentication-authorization/verify-app-proxy-requests
     */
    private function validateSignature(Request $request)
    {
        $signature = $request->get('signature');
        
        if (!$signature) {
            Log::warning('No signature provided in app proxy request');
            return false;
        }

        // Get all query parameters except signature
        $params = $request->query();
        unset($params['signature']);

        // Sort parameters by key
        ksort($params);

        // Build query string for signature validation
        $queryString = http_build_query($params);
        
        // Shopify app proxy signature validation uses the API secret
        $apiSecret = config('shopify.api_secret') ?? env('SHOPIFY_API_SECRET_KEY');
        
        if (!$apiSecret) {
            Log::error('Shopify API secret not configured');
            return false;
        }

        // Generate HMAC
        $computedSignature = hash_hmac('sha256', $queryString, $apiSecret);

        Log::info('Signature validation details', [
            'query_string_for_signature' => $queryString,
            'provided_signature' => $signature,
            'computed_signature' => $computedSignature,
            'signatures_match' => hash_equals($computedSignature, $signature)
        ]);

        return hash_equals($computedSignature, $signature);
    }

    public function unsubscribeCustomer(Request $request)
    {
        $email = $request->query('email');
        $shopDomain = $request->query('shop');

        if (!$email || !$shopDomain) {
            Log::error('Missing required parameters', [
                'email' => $email,
                'shop' => $shopDomain
            ]);
            return response()->json(['error' => 'Missing email or shop parameter'], 400);
        }

        // Get access token from SQLite database
        $accessToken = ShopStorage::get($shopDomain);
        
        if (!$accessToken) {
            Log::error("Access token not found for shop: {$shopDomain}");
            return response()->json(['error' => 'Shop not found or not authenticated'], 404);
        }

        try {
            // Search for customer
            $searchResponse = Http::withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
            ])->timeout(30)->get("https://{$shopDomain}/admin/api/2024-04/customers/search.json", [
                'query' => $email
            ]);

            if ($searchResponse->failed()) {
                Log::error("Failed to search for customer", [
                    'email' => $email,
                    'shop' => $shopDomain,
                    'status' => $searchResponse->status(),
                    'response' => $searchResponse->body()
                ]);
                return response()->json(['error' => 'Failed to search for customer'], 500);
            }

            $customers = $searchResponse->json('customers', []);
            
            if (empty($customers)) {
                Log::error("Customer not found: {$email} in shop: {$shopDomain}");
                return response()->json(['error' => 'Customer not found'], 404);
            }

            $customer = $customers[0];
            $customerId = $customer['id'];
            $emailConsent = $customer['email_marketing_consent']['state'] ?? null;

            Log::info("Found customer", [
                'customer_id' => $customerId,
                'email' => $email,
                'current_consent' => $emailConsent
            ]);

            if ($emailConsent === 'unsubscribed') {
                return response()->json(['message' => 'Customer is already unsubscribed']);
            }

            // Update customer to unsubscribed
            $updateResponse = Http::withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
                'Content-Type' => 'application/json'
            ])->timeout(30)->put("https://{$shopDomain}/admin/api/2024-04/customers/{$customerId}.json", [
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
                    'status' => $updateResponse->status(),
                    'response' => $updateResponse->json()
                ]);
                return response()->json(['error' => 'Failed to unsubscribe customer'], 500);
            }

        } catch (\Exception $e) {
            Log::error("Exception during unsubscribe process", [
                'email' => $email,
                'shop' => $shopDomain,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
}