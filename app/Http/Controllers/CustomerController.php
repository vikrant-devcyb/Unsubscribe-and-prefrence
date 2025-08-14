<?php
 
namespace App\Http\Controllers;
 
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use App\Helpers\ShopStorage;
 
class CustomerController extends Controller
{
    public function updateCustomerTags(Request $request): JsonResponse
    {
        $tags = implode(', ', $request->tags);
        $customerId = $request->customer_id;

        $shopifyDomain = env('SHOPIFY_SHOP');
        // $encrypted = ShopStorage::get($shopifyDomain);
        // $accessToken = ShopStorage::decryptToken($encrypted);
        $accessToken = env('SHOPIFY_ACCESS_TOKEN');
        $url = "https://{$shopifyDomain}/admin/api/2025-07/customers/{$customerId}.json";

        $client = new \GuzzleHttp\Client();
        $response = $client->put($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Shopify-Access-Token' => $accessToken,
            ],
            'json' => [
                'customer' => [
                    'id' => $customerId,
                    'tags' => $tags,
                ],
            ],
        ]);

        return $this->corsResponse([
            'status' => 'success',
            'shopify_response'   => json_decode($response->getBody(), true),
        ]);
    } 

    public function getCustomerTags(Request $request): JsonResponse
    {
        $customerId = $request->query('customer_id');

        $shopifyDomain = env('SHOPIFY_SHOP');
        // $encrypted = ShopStorage::get($shopifyDomain);
        // $accessToken = ShopStorage::decryptToken($encrypted);
        $accessToken = env('SHOPIFY_ACCESS_TOKEN');

        $url = "https://{$shopifyDomain}/admin/api/2025-07/customers/{$customerId}.json";

        $client = new \GuzzleHttp\Client();
        $response = $client->get($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Shopify-Access-Token' => $accessToken,
            ],
        ]);

        $customer = json_decode($response->getBody(), true)['customer'];

        return $this->corsResponse([
            'tags' => $customer['tags'] ?? ''
        ]);
    }

 
    protected function corsResponse(array $data, int $status = 200): JsonResponse
    {
        $origin = request()->headers->get('Origin');
        // Whitelist Shopify store domain only
        $shopifyDomain = env('SHOPIFY_SHOP');
        $allowedOrigins = ['https://' . $shopifyDomain];
        if (!in_array($origin, $allowedOrigins)) {
            return response()->json(['error' => 'CORS not allowed'], 403);
        }

        return response()->json($data, $status)
            ->header('Access-Control-Allow-Origin', $origin)
            ->header('Vary', 'Origin')
            ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    }

}