<?php

namespace App\Monitoring\Contracts;

interface MonitoringDriver
{
    public function isConfigured(): bool;

    public function isAvailable(): bool;

    /**
     * @return array<int, array{metric: array<string, string>, value: array{0: float|string, 1: string}}>
     */
    public function query(string $expression): array;

    /**
     * @return array<int, array{metric: array<string, string>, values: array<int, array{0: float|string, 1: string}>}>
     */
    public function queryRange(string $expression, int $start, int $end, int $step): array;
}
