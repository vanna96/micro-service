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
        $currencyPrecisions = Currency::query()
            ->whereIn('id', $validCurrencyIds)
            ->pluck('decimal_places', 'id')
            ->map(fn ($places) => max((int) $places, 0));

        DB::connection($this->tenantConnectionName())->transaction(function () use ($datasetType, $year, $month, $validCurrencyIds, $cells, $daysInMonth, $currencyPrecisions) {
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

                    $decimalPlaces = (int) ($currencyPrecisions->get($currencyId, 0));
                    $trimmedValue = number_format((float) $trimmedValue, $decimalPlaces, '.', '');

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

    public function getLatestExchangeRates(?Currency $baseCurrency = null): array
    {
        $baseCurrency = $baseCurrency ?: tenant_base_currency();
        $baseCurrencyCode = strtoupper(trim((string) ($baseCurrency?->code ?: 'USD')));
        $baseCurrencyId = $baseCurrency?->id;

        $ratesById = $baseCurrencyId ? [(int) $baseCurrencyId => 1.0] : [];
        $ratesByCode = [$baseCurrencyCode => 1.0];

        $latestRateRows = RateIndexValue::query()
            ->with('currency')
            ->where('dataset_type', 'exchange_rate')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderByDesc('day')
            ->get()
            ->unique('currency_id');

        $latestDates = [];
        $remarks = [];
        foreach ($latestRateRows as $row) {
            $val = (float) $row->value;
            if ($val > 0) {
                $ratesById[(int) $row->currency_id] = $val;
                $formattedDate = sprintf('%04d-%02d-%02d', $row->year, $row->month, $row->day);
                $latestDates[(int) $row->currency_id] = $formattedDate;
                if ($row->currency && $row->currency->code) {
                    $code = strtoupper(trim((string) $row->currency->code));
                    $ratesByCode[$code] = $val;
                    $dec = max((int) ($row->currency->decimal_places ?? 0), 0);
                    $remarks[$code] = "1 {$baseCurrencyCode} = " . number_format($val, $dec, '.', ',') . " {$code} (Latest: {$formattedDate})";
                }
            }
        }

        $allCurrencies = Currency::query()->where('status', 'Active')->get();
        foreach ($allCurrencies as $curr) {
            $cId = (int) $curr->id;
            $cCode = strtoupper(trim((string) $curr->code));
            if (! isset($ratesById[$cId])) {
                $ratesById[$cId] = 1.0;
            }
            if (! isset($ratesByCode[$cCode])) {
                $ratesByCode[$cCode] = 1.0;
            }
        }

        return [
            'by_id' => $ratesById,
            'by_code' => $ratesByCode,
            'dates' => $latestDates,
            'remarks' => $remarks,
        ];
    }

    public function getRatesForDate($date = null, ?Currency $baseCurrency = null): array
    {
        $tenantSettings = tenant('general_settings') ?: [];
        $tenantTimezone = (string) ($tenantSettings['timezone'] ?? config('app.timezone', 'UTC'));

        $dateObj = $date ? Carbon::parse($date, $tenantTimezone) : Carbon::now($tenantTimezone);
        $baseCurrency = $baseCurrency ?: tenant_base_currency();
        $baseCurrencyCode = strtoupper(trim((string) ($baseCurrency?->code ?: 'USD')));
        $baseCurrencyId = $baseCurrency?->id;

        $ratesById = $baseCurrencyId ? [(int) $baseCurrencyId => 1.0] : [];
        $ratesByCode = [$baseCurrencyCode => 1.0];
        $configuredCurrencyIds = $baseCurrencyId ? [(int) $baseCurrencyId] : [];
        $configuredCodes = [$baseCurrencyCode];

        $rateRows = RateIndexValue::query()
            ->with('currency')
            ->where('dataset_type', 'exchange_rate')
            ->where('year', $dateObj->year)
            ->where('month', $dateObj->month)
            ->where('day', $dateObj->day)
            ->get();

        $remarks = [];
        $dateFormatted = $dateObj->format('Y-m-d');

        foreach ($rateRows as $row) {
            $val = (float) $row->value;
            if ($val > 0) {
                $cId = (int) $row->currency_id;
                $ratesById[$cId] = $val;
                $configuredCurrencyIds[] = $cId;
                if ($row->currency && $row->currency->code) {
                    $code = strtoupper(trim((string) $row->currency->code));
                    $ratesByCode[$code] = $val;
                    $configuredCodes[] = $code;
                    $dec = max((int) ($row->currency->decimal_places ?? 0), 0);
                    $dateLabel = $dateObj->isToday() ? "Today: {$dateFormatted}" : "Date: {$dateFormatted}";
                    $remarks[$code] = "1 {$baseCurrencyCode} = " . number_format($val, $dec, '.', ',') . " {$code} ({$dateLabel})";
                }
            }
        }

        $allCurrencies = Currency::query()->where('status', 'Active')->get();
        foreach ($allCurrencies as $curr) {
            $cId = (int) $curr->id;
            $cCode = strtoupper(trim((string) $curr->code));
            if (! isset($ratesById[$cId])) {
                $ratesById[$cId] = 1.0;
            }
            if (! isset($ratesByCode[$cCode])) {
                $ratesByCode[$cCode] = 1.0;
            }
        }

        return [
            'date' => $dateFormatted,
            'is_today' => $dateObj->isToday(),
            'by_id' => $ratesById,
            'by_code' => $ratesByCode,
            'configured_ids' => array_values(array_unique($configuredCurrencyIds)),
            'configured_codes' => array_values(array_unique($configuredCodes)),
            'remarks' => $remarks,
            'manage_url' => route('admin.rate-index.index', [
                'year' => $dateObj->year,
                'month' => $dateObj->month,
            ]),
        ];
    }

    protected function tenantConnectionName(): string
    {
        if (! tenant()) {
            return 'central';
        }

        return tenant()->database_connection_name ?: 'tenant';
    }
}
