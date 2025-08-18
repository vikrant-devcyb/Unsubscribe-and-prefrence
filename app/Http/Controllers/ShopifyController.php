<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Helpers\ShopStorage;
use App\Jobs\InjectScriptTagToShop;

class ShopifyController extends Controller
{
    /**
     * Intall Shopify APP
     * Allow Some Scopes
    */
    public function install(Request $request)
    {
        $shop = $request->get('shop');
        $scopes = 'read_products,read_inventory,read_locations,read_script_tags,write_script_tags,read_customers,write_customers';
        $redirectUri = urlencode(env('APP_URL') . '/shopify/callback');
        $apiKey = env('SHOPIFY_API_KEY');
        $installUrl = "https://{$shop}/admin/oauth/authorize?client_id={$apiKey}&scope={$scopes}&redirect_uri={$redirectUri}&state=123&grant_options[]=per-user";
        return redirect($installUrl);
    }

    /**
     * Call Back Function
     * Handle Call back for Shopify
    */
    public function callback(Request $request)
    {
        if (!$this->validateHmac($request->all(), $request->get('hmac'))) {
            abort(403, 'Invalid HMAC');
        }

        $shop = $request->get('shop');
        $code = $request->get('code');
        $apiSecret = env('SHOPIFY_API_SECRET_KEY');
        $apiKey = env('SHOPIFY_API_KEY');

        $response = Http::post("https://{$shop}/admin/oauth/access_token", [
            'client_id' => $apiKey,
            'client_secret' => $apiSecret,
            'code' => $code,
        ]);
        $accessToken = $response['access_token'];
        
        ShopStorage::set($shop, $accessToken);
        session(['shop' => $shop, 'access_token' => $accessToken]);

        // Inject ScriptTag with APP_URL
        InjectScriptTagToShop::dispatch($shop, $accessToken); // using job

        echo"<pre>"; print_r($accessToken);  die;

        $lastDate = '05-07-2025';
        return view('shopify.installed', [
            'shop' => $shop,
            'unsubscribedCount' => '12345',
            'lastUnsubscribedAt' => $lastDate ? \Carbon\Carbon::parse($lastDate)->format('d M Y, h:i A') : null,
        ]);
    }

    /**
     * Shopify ValidateHmac 
    */
    private function validateHmac($params, $hmac)
    {
        unset($params['hmac'], $params['signature']);
        ksort($params);
        $computedHmac = hash_hmac('sha256', http_build_query($params), env('SHOPIFY_API_SECRET_KEY'));
        return hash_equals($computedHmac, $hmac);
    }
}

