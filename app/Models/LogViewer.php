<?php

namespace App\Models;

use Illuminate\Support\Facades\File;

class LogViewer
{
    public static function getLogs($lines = 200)
    {
        $logFile = storage_path('logs/laravel.log');

        if (!File::exists($logFile)) {
            return ["Log file not found"];
        }

        $content = File::get($logFile);
        $logLines = explode("\n", $content);

        return array_slice($logLines, -$lines);
    }
}
