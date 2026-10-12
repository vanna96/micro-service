<?php

declare(strict_types=1);

use App\Cache\CatalogCacheTags;
use App\Contracts\CatalogCacheInvalidator;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$tenant = (string) ($argv[1] ?? 'rechna');
$path = (string) ($argv[2] ?? '/v1/api/mobile/bootstrap');
$activeSample = null;
$samples = [];

Event::listen(QueryExecuted::class, function (QueryExecuted $query) use (&$activeSample, &$samples): void {
    if ($activeSample === null) {
        return;
    }

    $samples[$activeSample]['queries']++;
    $samples[$activeSample]['sql_ms'] += $query->time;
    $connection = $query->connectionName ?: 'default';
    $samples[$activeSample]['connections'][$connection] =
        ($samples[$activeSample]['connections'][$connection] ?? 0) + 1;
});

$app->make(CatalogCacheInvalidator::class)->invalidate([
    CatalogCacheTags::ITEMS,
    CatalogCacheTags::CATEGORIES,
    CatalogCacheTags::BRANCHES,
    CatalogCacheTags::BANNERS,
    CatalogCacheTags::CURRENCIES,
    CatalogCacheTags::RATES,
    CatalogCacheTags::PROMOTIONS,
    CatalogCacheTags::BOOTSTRAP,
], $tenant);

foreach (['cold', 'warm'] as $name) {
    $samples[$name] = [
        'queries' => 0,
        'sql_ms' => 0.0,
        'connections' => [],
    ];
    $activeSample = $name;
    $started = hrtime(true);
    $request = Request::create($path, 'GET', [], [], [], [
        'HTTP_HOST' => $tenant.'.vanna-pos.duckdns.org',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_TENANT' => $tenant,
        'REMOTE_ADDR' => '127.0.0.1',
    ]);
    $response = $kernel->handle($request);
    $activeSample = null;
    $samples[$name]['status'] = $response->getStatusCode();
    $samples[$name]['duration_ms'] = round((hrtime(true) - $started) / 1e6, 2);
    $samples[$name]['sql_ms'] = round($samples[$name]['sql_ms'], 2);
    $samples[$name]['response_bytes'] = strlen((string) $response->getContent());
    $samples[$name]['query_cache'] = $request->attributes->get('query_cache_telemetry', []);
    $kernel->terminate($request, $response);
}

echo json_encode([
    'tenant' => $tenant,
    'path' => $path,
    'samples' => $samples,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
