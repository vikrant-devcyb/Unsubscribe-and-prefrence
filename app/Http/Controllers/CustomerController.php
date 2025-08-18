<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Helpers\ShopStorage;

class CustomerController extends Controller
{
    private $apiVersion = '2024-04'; // Standardized API version

    public function updateCustomerTags(Request $request): JsonResponse
    {
        try {
            $tags = implode(', ', $request->tags);
            $customerId = $request->customer_id;
            $shopifyDomain = env('SHOPIFY_SHOP');

            // Get access token from SQLite database
            $accessToken = ShopStorage::get($shopifyDomain);
            
            if (!$accessToken) {
                Log::error("Access token not found for shop: {$shopifyDomain}");
                return $this->corsResponse(['error' => 'Shop not authenticated'], 401);
            }

            $url = "https://{$shopifyDomain}/admin/api/{$this->apiVersion}/customers/{$customerId}.json";

            // Use Http facade instead of Guzzle for consistency
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Shopify-Access-Token' => $accessToken,
            ])->put($url, [
                'customer' => [
                    'id' => $customerId,
                    'tags' => $tags,
                ],
            ]);

            if ($response->successful()) {
                Log::info("Customer tags updated successfully: {$customerId}");
                return $this->corsResponse([
                    'status' => 'success',
                    'shopify_response' => $response->json(),
                ]);
            } else {
                Log::error("Failed to update customer tags: {$customerId}", [
                    'response' => $response->json()
                ]);
                return $this->corsResponse(['error' => 'Failed to update customer tags'], 500);
            }

        } catch (\Exception $e) {
            Log::error("Exception updating customer tags: " . $e->getMessage());
            return $this->corsResponse(['error' => 'Internal server error'], 500);
        }
    }

    public function getCustomerTags(Request $request): JsonResponse
    {
        try {
            $customerId = $request->query('customer_id');

            if (!$customerId) {
                return $this->corsResponse(['error' => 'Customer ID is required'], 400);
            }

            $shopifyDomain = env('SHOPIFY_SHOP');
            
            // Get access token from SQLite database
            $accessToken = ShopStorage::get($shopifyDomain);
            
            if (!$accessToken) {
                Log::error("Access token not found for shop: {$shopifyDomain}");
                return $this->corsResponse(['error' => 'Shop not authenticated'], 401);
            }

            $url = "https://{$shopifyDomain}/admin/api/{$this->apiVersion}/customers/{$customerId}.json";

            // Use Http facade for consistency
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Shopify-Access-Token' => $accessToken,
            ])->get($url);

            if ($response->successful()) {
                $customer = $response->json()['customer'];
                Log::info("Customer tags retrieved successfully: {$customerId}");
                return $this->corsResponse([
                    'tags' => $customer['tags'] ?? ''
                ]);
            } else {
                Log::error("Failed to get customer tags: {$customerId}", [
                    'response' => $response->json()
                ]);
                return $this->corsResponse(['error' => 'Failed to get customer tags'], 500);
            }

        } catch (\Exception $e) {
            Log::error("Exception getting customer tags: " . $e->getMessage());
            return $this->corsResponse(['error' => 'Internal server error'], 500);
        }
    }

    protected function corsResponse(array $data, int $status = 200): JsonResponse
    {
        $origin = request()->headers->get('Origin');
        $shopifyDomain = env('SHOPIFY_SHOP');
        $allowedOrigins = [
            'https://' . $shopifyDomain,
            'https://brownsfashion.com'
        ];

        if (!in_array($origin, $allowedOrigins)) {
            Log::warning("CORS not allowed for origin: {$origin}");
            return response()->json(['error' => 'CORS not allowed'], 403);
        }

        return response()->json($data, $status)
            ->header('Access-Control-Allow-Origin', $origin)
            ->header('Vary', 'Origin')
            ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    }
}