<?php

namespace App\Http\Middleware;

use App\Monitoring\Services\MetricsRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class TrackMonitoringRequest
{
    private MetricsRepository $metrics;

    public function __construct(MetricsRepository $metrics)
    {
        $this->metrics = $metrics;
    }

    public function handle(Request $request, Closure $next)
    {
        if (! $this->metrics->enabled() || $request->is('internal/metrics')) {
            return $next($request);
        }

        $startedAt = microtime(true);
        $status = 500;
        $this->metrics->beginRequest();

        try {
            $response = $next($request);
            $status = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 200;

            return $response;
        } catch (Throwable $e) {
            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();
            }
            throw $e;
        } finally {
            $route = $request->route();
            $routeLabel = $route?->getName() ?: $route?->uri() ?: 'unmatched';
            $this->metrics->recordRequest(
                $request->method(),
                (string) $routeLabel,
                (int) $status,
                microtime(true) - $startedAt,
                memory_get_peak_usage(true)
            );
        }
    }
}
