<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Repositories\CurrencyRepository;
use App\Repositories\RateIndexRepository;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class RateIndexController extends Controller
{
    protected RateIndexRepository $rateIndexes;
    protected CurrencyRepository $currencies;

    public function __construct(RateIndexRepository $rateIndexes, CurrencyRepository $currencies)
    {
        $this->rateIndexes = $rateIndexes;
        $this->currencies = $currencies;
        $this->middleware('admin.permission:rate_indexes.view')->only(['index']);
        $this->middleware('admin.permission:rate_indexes.manage')->except(['index']);
    }

    public function index(Request $request): View
    {
        $selectedTenant = $this->requiredTenant($request);
        $year = max((int) $request->get('year', now()->year), 2000);
        $month = min(max((int) $request->get('month', now()->month), 1), 12);
        $baseCurrencyCode = strtoupper(trim((string) data_get($selectedTenant->general_settings, 'currency', '')));
        $baseCurrency = $this->currencies->findActiveByCode($baseCurrencyCode);
        $currencies = $baseCurrency
            ? $this->rateIndexes->getSelectableCurrencies($baseCurrency->code)
            : collect();
        $datasetType = 'exchange_rate';

        $gridValues = $baseCurrency && $currencies->isNotEmpty()
            ? $this->rateIndexes->getGridValues($datasetType, $year, $month, $currencies->pluck('id')->all())
            : [];

        return view('admin.rate-index.index', [
            'selectedTenant' => $selectedTenant,
            'datasetType' => $datasetType,
            'selectedYear' => $year,
            'selectedMonth' => $month,
            'baseCurrency' => $baseCurrency,
            'currencies' => $currencies,
            'gridValues' => $gridValues,
            'days' => range(1, 31),
            'daysInMonth' => Carbon::create($year, $month, 1)->daysInMonth,
            'monthLabel' => Carbon::create($year, $month, 1)->format('F'),
            'yearOptions' => range(now()->year - 2, now()->year + 3),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $selectedTenant = $this->requiredTenant($request);
        $validated = $this->validatePayload($request);
        $datasetType = 'exchange_rate';
        $baseCurrencyCode = strtoupper(trim((string) data_get($selectedTenant->general_settings, 'currency', '')));
        $baseCurrency = $this->currencies->findActiveByCode($baseCurrencyCode);

        if (! $baseCurrency) {
            return redirect()
                ->route('admin.rate-index.index', [
                    'year' => $validated['year'],
                    'month' => $validated['month'],
                ])
                ->with('status', 'Set an active base currency in General settings before editing the rate index.');
        }

        $currencies = $this->rateIndexes->getSelectableCurrencies($baseCurrency->code);
        $validated = $this->validateGridCells($request, $validated, $currencies);

        $this->rateIndexes->saveGridValues(
            $datasetType,
            (int) $validated['year'],
            (int) $validated['month'],
            $currencies->pluck('id')->all(),
            $validated['cells'] ?? []
        );

        return redirect()
            ->route('admin.rate-index.index', [
                'year' => $validated['year'],
                'month' => $validated['month'],
            ])
            ->with('status', 'Exchange rates updated successfully.');
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'cells' => ['nullable', 'array'],
            'cells.*' => ['array'],
            'cells.*.*' => ['nullable', 'numeric', 'gt:0'],
        ]);
    }

    private function validateGridCells(Request $request, array $validated, \Illuminate\Support\Collection $currencies): array
    {
        $year = (int) $validated['year'];
        $month = (int) $validated['month'];
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $currencyMap = $currencies->keyBy(fn ($currency) => (string) $currency->id);

        $validator = Validator::make($request->all(), []);

        $validator->after(function ($validator) use ($request, $daysInMonth, $currencyMap) {
            foreach ((array) $request->input('cells', []) as $day => $currencyValues) {
                $day = (int) $day;

                if ($day < 1 || $day > $daysInMonth || ! is_array($currencyValues)) {
                    continue;
                }

                foreach ($currencyValues as $currencyId => $rawValue) {
                    $rawValue = trim((string) $rawValue);

                    if ($rawValue === '') {
                        continue;
                    }

                    $currency = $currencyMap->get((string) $currencyId);

                    if (! $currency) {
                        continue;
                    }

                    $decimalPart = str_contains($rawValue, '.')
                        ? explode('.', $rawValue, 2)[1]
                        : '';
                    $decimalCount = strlen($decimalPart);

                    if ($decimalCount > (int) $currency->decimal_places) {
                        $validator->errors()->add(
                            "cells.$day.$currencyId",
                            $currency->code . ' allows up to ' . $currency->decimal_places . ' decimal place(s).'
                        );
                    }
                }
            }
        });

        $validator->validate();

        return $validated;
    }

    private function requiredTenant(Request $request): Tenant
    {
        $tenant = admin_current_tenant();

        abort_if(! $tenant, 404);

        return $tenant;
    }
}
