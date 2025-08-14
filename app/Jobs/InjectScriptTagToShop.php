<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InjectScriptTagToShop implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $shopDomain;
    protected $accessToken;

    public function __construct($shopDomain, $accessToken)
    {
        $this->shopDomain = $shopDomain;
        $this->accessToken = $accessToken;
    }

    public function handle()
    {
        $scripts = ['/unsubscribe.js'];

        foreach ($scripts as $scriptPath) {
            $scriptUrl = rtrim(env('APP_URL'), '/') . $scriptPath;
            try {
                // Remove existing ScriptTag with same src (optional cleanup)
                $existing = Http::withHeaders([
                    'X-Shopify-Access-Token' => $this->accessToken
                ])->get("https://{$this->shopDomain}/admin/api/2024-04/script_tags.json");

                foreach ($existing['script_tags'] ?? [] as $tag) {
                    if ($tag['src'] === $scriptUrl) {
                        Http::withHeaders([
                            'X-Shopify-Access-Token' => $this->accessToken
                        ])->delete("https://{$this->shopDomain}/admin/api/2024-04/script_tags/{$tag['id']}.json");
                    }
                }

                // Inject new ScriptTag
                $inject = Http::withHeaders([
                    'X-Shopify-Access-Token' => $this->accessToken
                ])->post("https://{$this->shopDomain}/admin/api/2024-04/script_tags.json", [
                    'script_tag' => [
                        'event' => 'onload',
                        'src' => $scriptUrl,
                    ]
                ]);

                if (in_array($inject->status(), [200, 201])) {
                    Log::info("ScriptTag injected", [
                        'shop' => $this->shopDomain,
                        'script' => $scriptUrl,
                        'id' => $inject['script_tag']['id'] ?? 'unknown'
                    ]);
                } else {
                    Log::warning("Failed to inject ScriptTag", [
                        'shop' => $this->shopDomain,
                        'script' => $scriptUrl,
                        'response' => $inject->body()
                    ]);
                }

            } catch (\Exception $e) {
                Log::error("ScriptTag injection failed", [
                    'shop' => $this->shopDomain,
                    'script' => $scriptUrl,
                    'error' => $e->getMessage()
                ]);

                throw $e; // Will be retried by queue system
            }
        }
    }
}

