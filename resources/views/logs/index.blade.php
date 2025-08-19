<!DOCTYPE html>
<html>
<head>
    <title>Laravel Logs</title>
    <style>
        body { font-family: monospace; background: #111; color: #eee; padding: 20px; }
        pre { white-space: pre-wrap; word-wrap: break-word; background: #222; padding: 15px; border-radius: 5px; }
        .line { margin: 0; padding: 2px 0; border-bottom: 1px solid #333; }
    </style>
</head>
<body>
    <h2>Laravel Logs (last {{ count($logs) }} lines)</h2>
    <pre>
@foreach ($logs as $line)
    <div class="line">{{ $line }}</div>
@endforeach
    </pre>
</body>
</html>
