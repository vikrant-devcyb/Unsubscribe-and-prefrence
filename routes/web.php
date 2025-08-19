<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShopifyController;
use App\Http\Controllers\ProxyController;
use App\Helpers\ShopStorage;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;

Route::get('/clear-cache', function () {
    Artisan::call('config:clear');
    Artisan::call('cache:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    
    return 'Config, cache, route, and view caches cleared!';
});

// OAuth start
Route::get('/shopify/install', [ShopifyController::class, 'install']);
Route::get('/shopify/callback', [ShopifyController::class, 'callback'])->name('shopify.callback');

// App proxy handler
Route::get('/unsubscribe-preference-proxy-handler', [ProxyController::class, 'handle']);

Route::get('/', function (Request $request) {
    $shop = request()->get('shop'); // Get ?shop= param if passed
    
    if (!$shop) {
        return view('welcome');
        //return response("Missing shop parameter.", 400);
    }
    // $encrypted = ShopStorage::get($shop);
    // $accessToken = ShopStorage::decryptToken($encrypted);
    
    $accessToken = ShopStorage::get($shop);
    // $accessToken = env('SHOPIFY_ACCESS_TOKEN');
    if ($accessToken) {
        return view('shopify.dashboard', ['shop' => $shop]);
    } else {
        return view('shopify.not_installed', ['shop' => $shop]);
    }
})->name('shopify.home');


/**
 * Tail the last N lines of a file efficiently.
 */
function tail_file(string $path, int $lines = 2000): array
{
    if (!File::exists($path)) return [];

    $f = new SplFileObject($path, 'r');
    $f->seek(PHP_INT_MAX);
    $last = $f->key();

    $buffer = [];
    for ($line = 0; $line <= $last && count($buffer) < $lines; $line++) {
        $f->seek($last - $line);
        $buffer[] = rtrim($f->current(), "\r\n");
    }
    return array_reverse($buffer);
}

/**
 * Parse Laravel log lines into entries:
 * [YYYY-mm-dd HH:MM:SS] env.LEVEL: message
 * …(context/stack follows until the next entry)
 */
function parse_laravel_log(array $lines): array
{
    $entries = [];
    $current = null;

    $pattern = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+([a-zA-Z0-9_]+)\.([A-Z]+):\s(.*)$/';

    foreach ($lines as $line) {
        if (preg_match($pattern, $line, $m)) {
            // flush previous
            if ($current) $entries[] = $current;

            $current = [
                'timestamp' => $m[1],
                'env'       => $m[2],
                'level'     => $m[3],
                'message'   => $m[4],
                'context'   => '',
                'raw'       => $line,
            ];
        } else {
            if ($current) {
                $current['context'] .= ($current['context'] ? "\n" : '') . $line;
            } else {
                // lines that precede a first match (rare) — keep as generic entry
                $entries[] = [
                    'timestamp' => null,
                    'env'       => null,
                    'level'     => null,
                    'message'   => $line,
                    'context'   => '',
                    'raw'       => $line,
                ];
            }
        }
    }
    if ($current) $entries[] = $current;

    return $entries;
}

/**
 * Pick the newest log file in storage/logs.
 */
function latest_log_file(): ?string
{
    $files = glob(storage_path('logs') . DIRECTORY_SEPARATOR . '*.log');
    if (!$files) return null;
    usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
    return $files[0] ?? null;
}

Route::get('/view-debug-logs', function (Request $request) {
    // Optional: simple token guard
    // abort_unless($request->query('token') === config('app.log_view_token'), 403);

    $entriesLimit = (int) $request->query('entries', 50);  // number of entries to render
    $tailLines    = (int) $request->query('tail', 4000);   // how many raw lines to tail
    $query        = trim((string) $request->query('q', '')); // text filter

    $file = latest_log_file();

    $html = '
    <!DOCTYPE html>
    <html><head>
        <meta charset="utf-8" />
        <title>Debug Logs - Laravel</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Arial, sans-serif; margin: 20px; }
            .meta { color: #666; font-size: 13px; }
            .toolbar a, .toolbar form button { background: #444; color: #fff; padding: 8px 10px; border-radius: 4px; text-decoration: none; border: 0; cursor: pointer; }
            .toolbar form { display:inline-block; margin:0; }
            .toolbar input { padding:7px 9px; }
            .entry { border: 1px solid #e5e5e5; padding: 12px; border-radius: 6px; margin: 12px 0; }
            .head { display:flex; gap:10px; flex-wrap:wrap; }
            .ts { color:#555; }
            .lvl { font-weight:bold; }
            .env { color:#0a7; }
            .msg { margin: 8px 0; white-space: pre-wrap; word-break: break-word; }
            .ctx { background:#f9f9f9; border-radius:4px; padding:10px; white-space: pre; overflow:auto; max-height: 240px; }
            .badge { display:inline-block; padding:2px 6px; border-radius:4px; font-size:12px; color:#fff; }
            .ERROR { background:#e11; }
            .WARNING, .WARN { background:#e7a500; }
            .INFO { background:#0aa; }
            .DEBUG { background:#888; }
            .SUCCESS { background:#16a34a; }
            .footer { margin-top: 18px; font-size: 12px; color:#666; }
            .no { color:#999; }
        </style>
    </head><body>';

    $html .= '<h1>Laravel Log Viewer</h1>';

    if (!$file) {
        $html .= '<p>No log file found in <code>storage/logs</code>.</p></body></html>';
        return $html;
    }

    $lines   = tail_file($file, $tailLines);
    $entries = parse_laravel_log($lines);

    // Filter by query if provided
    if ($query !== '') {
        $entries = array_values(array_filter($entries, function ($e) use ($query) {
            $hay = strtolower(($e['message'] ?? '') . "\n" . ($e['context'] ?? ''));
            return str_contains($hay, strtolower($query));
        }));
    }

    // Keep the newest N
    $entries = array_slice(array_reverse($entries), 0, max(1, $entriesLimit));

    // Toolbar
    $html .= '<div class="toolbar" style="margin:10px 0 16px;">
        <span class="meta">File: <code>' . e(basename($file)) . '</code> ('.e(filesize($file)).' bytes)</span> &nbsp;|&nbsp;
        <a href="/view-debug-logs">Refresh</a> &nbsp;|&nbsp;
        <a href="/clear-debug-logs" onclick="return confirm(\'Clear all log files?\')">Clear logs</a>
        &nbsp;|&nbsp;
        <form action="/view-debug-logs" method="get" style="display:inline">
            <input type="text" name="q" value="'.e($query).'" placeholder="Filter (e.g. proxy, signature, unsubscribe)">
            <input type="hidden" name="entries" value="'.$entriesLimit.'">
            <button type="submit">Search</button>
        </form>
    </div>';

    if (empty($entries)) {
        $html .= '<p class="no">No matching entries.</p>';
    } else {
        foreach ($entries as $e) {
            $ts   = $e['timestamp'] ?: '—';
            $env  = $e['env'] ?: '—';
            $lvl  = $e['level'] ?: '—';
            $msg  = $e['message'] ?: '';
            $ctx  = trim($e['context'] ?? '');

            $html .= '<div class="entry">
                <div class="head">
                    <div class="ts">⏰ '.$ts.'</div>
                    <div class="env">🏷 '.$env.'</div>
                    <div class="lvl"><span class="badge '.$lvl.'">'.$lvl.'</span></div>
                </div>
                <div class="msg">'.nl2br(e($msg)).'</div>';
            if ($ctx !== '') {
                $html .= '<div class="ctx">'.e($ctx).'</div>';
            }
            $html .= '</div>';
        }
    }

    $html .= '<div class="footer">
        Showing '.count($entries).' entr'.(count($entries)===1?'y':'ies').'. 
        Use <code>?entries=100</code> or <code>?tail=8000</code> to adjust. 
        Filter with <code>?q=proxy</code>.
    </div>';

    $html .= '</body></html>';

    return $html;
});

/**
 * Clear all log files (truncate). Be careful in production.
 */
Route::get('/clear-debug-logs', function () {
    $dir = storage_path('logs');
    $files = glob($dir . DIRECTORY_SEPARATOR . '*.log');
    $count = 0;

    foreach ($files as $file) {
        // Truncate instead of delete to keep permissions/handles
        if (is_writable($file)) {
            $h = fopen($file, 'w');
            if ($h) { fclose($h); $count++; }
        }
    }

    return response()->json([
        'message'   => "Cleared {$count} log file(s)",
        'timestamp' => now()->toDateTimeString(),
    ]);
});