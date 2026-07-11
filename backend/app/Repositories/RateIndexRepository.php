<?php

namespace App\Repositories;

use App\Models\Currency;
use App\Models\RateIndexValue;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RateIndexRepository
{
    public function getGridValues(string $datasetType, int $year, int $month, array $currencyIds): array
    {
        if ($currencyIds === []) {
            return [];
        }

        return RateIndexValue::query()
            ->where('dataset_type', $datasetType)
            ->where('year', $year)
            ->where('month', $month)
            ->whereIn('currency_id', $currencyIds)
            ->get()
            ->groupBy('day')
            ->map(fn (Collection $rows) => $rows->mapWithKeys(fn (RateIndexValue $row) => [
                $row->currency_id => $row->value,
            ])->all())
            ->all();
    }

    public function saveGridValues(string $datasetType, int $year, int $month, array $currencyIds, array $cells): void
    {
        $validCurrencyIds = collect($currencyIds)->map(fn ($id) => (int) $id)->unique()->values()->all();
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;

        DB::connection($this->tenantConnectionName())->transaction(function () use ($datasetType, $year, $month, $validCurrencyIds, $cells, $daysInMonth) {
            foreach ($cells as $day => $currencyValues) {
                $day = (int) $day;

                if ($day < 1 || $day > $daysInMonth || ! is_array($currencyValues)) {
                    continue;
                }

                foreach ($currencyValues as $currencyId => $value) {
                    $currencyId = (int) $currencyId;

                    if (! in_array($currencyId, $validCurrencyIds, true)) {
                        continue;
                    }

                    $trimmedValue = trim((string) $value);

                    if ($trimmedValue === '') {
                        RateIndexValue::query()
                            ->where('dataset_type', $datasetType)
                            ->where('year', $year)
                            ->where('month', $month)
                            ->where('day', $day)
                            ->where('currency_id', $currencyId)
                            ->delete();

                        continue;
                    }

                    RateIndexValue::query()->updateOrCreate(
                        [
                            'dataset_type' => $datasetType,
                            'year' => $year,
                            'month' => $month,
                            'day' => $day,
                            'currency_id' => $currencyId,
                        ],
                        [
                            'value' => $trimmedValue,
                        ]
                    );
                }
            }

            RateIndexValue::flushQueryCache();
        });
    }

    public function getSelectableCurrencies(?string $baseCurrencyCode): Collection
    {
        $query = Currency::query()
            ->where('status', 'Active')
            ->orderBy('sort_order')
            ->orderBy('code');

        if ($baseCurrencyCode) {
            $query->where('code', '!=', strtoupper(trim($baseCurrencyCode)));
        }

        return $query->get();
    }

    protected function tenantConnectionName(): string
    {
        if (! tenant()) {
            return 'central';
        }

        return tenant()->database_connection_name ?: 'tenant';
    }
}
