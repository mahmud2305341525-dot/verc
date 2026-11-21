<?php

/*
|--------------------------------------------------------------------------
| Vercel serverless entry point for Laravel
|--------------------------------------------------------------------------
| The deployment filesystem is read-only except for /tmp, so everything
| Laravel writes at runtime (compiled views, cache files, logs, framework
| caches) is redirected there. Persistent data lives in TiDB Cloud (sessions,
| cache, users...) and in S3-compatible object storage (incident photos).
*/

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$root = dirname(__DIR__);
$tmp = rtrim(sys_get_temp_dir(), '/').'/nightlifebd';

foreach ([
    'storage/app/private/incident_photos',
    'storage/app/public',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
    'bootstrap-cache',
] as $dir) {
    if (! is_dir("$tmp/$dir")) {
        @mkdir("$tmp/$dir", 0775, true);
    }
}

// Laravel writes these on first boot; the default location (bootstrap/cache) is read-only here.
$defaults = [
    'APP_PACKAGES_CACHE' => "$tmp/bootstrap-cache/packages.php",
    'APP_SERVICES_CACHE' => "$tmp/bootstrap-cache/services.php",
    'APP_CONFIG_CACHE' => "$tmp/bootstrap-cache/config.php",
    'APP_ROUTES_CACHE' => "$tmp/bootstrap-cache/routes.php",
    'APP_EVENTS_CACHE' => "$tmp/bootstrap-cache/events.php",
    // Safe serverless defaults - override in the Vercel dashboard if needed
    'LOG_CHANNEL' => 'stderr',
    'QUEUE_CONNECTION' => 'sync',
];

foreach ($defaults as $key => $value) {
    if (getenv($key) === false && ! isset($_ENV[$key])) {
        putenv("$key=$value");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

require $root.'/vendor/autoload.php';

/** @var Illuminate\Foundation\Application $app */
$app = require_once $root.'/bootstrap/app.php';
$app->useStoragePath("$tmp/storage");

$app->handleRequest(Request::capture());
