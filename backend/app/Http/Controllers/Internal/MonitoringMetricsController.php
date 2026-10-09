<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Monitoring\Services\MetricsRepository;
use Illuminate\Http\Response;
use Throwable;

class MonitoringMetricsController extends Controller
{
    public function __invoke(MetricsRepository $metrics): Response
    {
        if (! $metrics->enabled() || ! config('monitoring.prometheus.enabled')) {
            abort(404);
        }

        try {
            return response($metrics->renderPrometheus(), 200, [
                'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
                'Cache-Control' => 'no-store',
            ]);
        } catch (Throwable) {
            return response("# metrics temporarily unavailable\n", 503, [
                'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
            ]);
        }
    }
}
