<?php

namespace App\Monitoring\Drivers;

use App\Monitoring\Contracts\MonitoringDriver;
use App\Monitoring\Exceptions\MonitoringUnavailableException;
use Illuminate\Support\Facades\Http;
use Throwable;

class PrometheusDriver implements MonitoringDriver
{
    public function isConfigured(): bool
    {
        return (bool) config('monitoring.enabled')
            && (bool) config('monitoring.prometheus.enabled')
            && trim((string) config('monitoring.prometheus.url')) !== '';
    }

    public function isAvailable(): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            return $this->client()->get($this->baseUrl().'/-/ready')->successful();
        } catch (Throwable) {
            return false;
        }
    }

    public function query(string $expression): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        try {
            $response = $this->client()->get($this->baseUrl().'/api/v1/query', [
                'query' => $expression,
            ]);

            if (! $response->successful() || $response->json('status') !== 'success') {
                throw new MonitoringUnavailableException('Prometheus returned an unsuccessful response.');
            }

            return (array) $response->json('data.result', []);
        } catch (MonitoringUnavailableException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new MonitoringUnavailableException('Prometheus is unavailable.', 0, $e);
        }
    }

    public function queryRange(string $expression, int $start, int $end, int $step): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        try {
            $response = $this->client()->get($this->baseUrl().'/api/v1/query_range', [
                'query' => $expression,
                'start' => $start,
                'end' => $end,
                'step' => max(15, $step),
            ]);

            if (! $response->successful() || $response->json('status') !== 'success') {
                throw new MonitoringUnavailableException('Prometheus returned an unsuccessful range response.');
            }

            return (array) $response->json('data.result', []);
        } catch (MonitoringUnavailableException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new MonitoringUnavailableException('Prometheus is unavailable.', 0, $e);
        }
    }

    private function client()
    {
        return Http::acceptJson()
            ->connectTimeout((int) config('monitoring.prometheus.connect_timeout', 1))
            ->timeout((int) config('monitoring.prometheus.timeout', 2));
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('monitoring.prometheus.url'), '/');
    }
}
