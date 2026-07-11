<?php

if (! function_exists('formatValidationErrors')) {
    function formatValidationErrors(array $errors): array
    {
        $fieldCount = count($errors);
        $firstField = array_key_first($errors);
        $firstMessage = $errors[$firstField][0] ?? 'Validation error';
        $message = $firstMessage;
        if ($fieldCount > 1) {
            $message .= " (and " . ($fieldCount - 1) . " more errors)";
        }

        return [
            'message' => $message,
            'errors' => $errors
        ];
    }
}

if (! function_exists('country_code')) {
    function country_code(): array
    {
        return [
            '1' => 'US',  // United States
            '20' => 'EG', // Egypt
            '27' => 'ZA', // South Africa
            '30' => 'GR', // Greece
            '31' => 'NL', // Netherlands
            '32' => 'BE', // Belgium
            '33' => 'FR', // France
            '34' => 'ES', // Spain
            '36' => 'HU', // Hungary
            '39' => 'IT', // Italy
            '40' => 'RO', // Romania
            '41' => 'CH', // Switzerland
            '42' => 'CZ', // Czech Republic
            '43' => 'AT', // Austria
            '44' => 'GB', // United Kingdom
            '45' => 'DK', // Denmark
            '46' => 'SE', // Sweden
            '47' => 'NO', // Norway
            '48' => 'PL', // Poland
            '49' => 'DE', // Germany
            '51' => 'PE', // Peru
            '52' => 'MX', // Mexico
            '53' => 'CU', // Cuba
            '54' => 'AR', // Argentina
            '55' => 'BR', // Brazil
            '56' => 'CL', // Chile
            '57' => 'CO', // Colombia
            '58' => 'VE', // Venezuela
            '60' => 'MY', // Malaysia
            '61' => 'AU', // Australia
            '62' => 'ID', // Indonesia
            '63' => 'PH', // Philippines
            '64' => 'NZ', // New Zealand
            '65' => 'SG', // Singapore
            '66' => 'TH', // Thailand
            '81' => 'JP', // Japan
            '82' => 'KR', // South Korea
            '84' => 'VN', // Vietnam
            '86' => 'CN', // China
            '90' => 'TR', // Turkey
            '91' => 'IN', // India
            '92' => 'PK', // Pakistan
            '93' => 'AF', // Afghanistan
            '94' => 'LK', // Sri Lanka
            '95' => 'MM', // Myanmar (Burma)
            '98' => 'IR', // Iran
            '212' => 'MA', // Morocco
            '213' => 'DZ', // Algeria
            '216' => 'TN', // Tunisia
            '218' => 'LY', // Libya
            '220' => 'GM', // Gambia
            '221' => 'SN', // Senegal
            '222' => 'MR', // Mauritania
            '223' => 'ML', // Mali
            '224' => 'GN', // Guinea
            '225' => 'CI', // Ivory Coast
            '226' => 'BF', // Burkina Faso
            '227' => 'NE', // Niger
            '228' => 'TG', // Togo
            '229' => 'BJ', // Benin
            '230' => 'MU', // Mauritius
            '231' => 'LR', // Liberia
            '232' => 'SL', // Sierra Leone
            '233' => 'GH', // Ghana
            '234' => 'NG', // Nigeria
            '235' => 'TD', // Chad
            '236' => 'CF', // Central African Republic
            '237' => 'CM', // Cameroon
            '238' => 'CV', // Cape Verde
            '239' => 'ST', // Sao Tome and Principe
            '240' => 'GQ', // Equatorial Guinea
            '241' => 'GA', // Gabon
            '242' => 'CG', // Republic of the Congo
            '243' => 'CD', // Democratic Republic of the Congo
            '244' => 'AO', // Angola
            '245' => 'GW', // Guinea-Bissau
            '246' => 'IO', // British Indian Ocean Territory
            '247' => 'AC', // Ascension Island
            '248' => 'SC', // Seychelles
            '249' => 'SD', // Sudan
            '250' => 'RW', // Rwanda
            '251' => 'ET', // Ethiopia
            '252' => 'SO', // Somalia
            '253' => 'DJ', // Djibouti
            '254' => 'KE', // Kenya
            '255' => 'TZ', // Tanzania
            '256' => 'UG', // Uganda
            '257' => 'BI', // Burundi
            '258' => 'MZ', // Mozambique
            '260' => 'ZM', // Zambia
            '261' => 'MG', // Madagascar
            '262' => 'RE', // Reunion
            '263' => 'ZW', // Zimbabwe
            '264' => 'NA', // Namibia
            '265' => 'MW', // Malawi
            '266' => 'LS', // Lesotho
            '267' => 'BW', // Botswana
            '268' => 'SZ', // Eswatini
            '269' => 'KM', // Comoros
            '290' => 'SH', // Saint Helena
            '291' => 'ER', // Eritrea
            '297' => 'AW', // Aruba
            '298' => 'FO', // Faroe Islands
            '299' => 'GL', // Greenland
            '350' => 'GI', // Gibraltar
            '351' => 'PT', // Portugal
            '352' => 'LU', // Luxembourg
            '353' => 'IE', // Ireland
            '354' => 'IS', // Iceland
            '355' => 'AL', // Albania
            '356' => 'MT', // Malta
            '357' => 'CY', // Cyprus
            '358' => 'FI', // Finland
            '359' => 'BG', // Bulgaria
            '370' => 'LT', // Lithuania
            '371' => 'LV', // Latvia
            '372' => 'EE', // Estonia
            '373' => 'MD', // Moldova
            '374' => 'AM', // Armenia
            '375' => 'BY', // Belarus
            '376' => 'AD', // Andorra
            '377' => 'MC', // Monaco
            '378' => 'SM', // San Marino
            '379' => 'VA', // Vatican City
            '380' => 'UA', // Ukraine
            '381' => 'RS', // Serbia
            '382' => 'ME', // Montenegro
            '383' => 'XK', // Kosovo
            '384' => 'AL', // Albania
            '385' => 'HR', // Croatia
            '386' => 'SI', // Slovenia
            '387' => 'BA', // Bosnia and Herzegovina
            '389' => 'MK', // North Macedonia
            '420' => 'CZ', // Czech Republic
            '421' => 'SK', // Slovakia
            '423' => 'LI', // Liechtenstein
            '500' => 'FK', // Falkland Islands
            '501' => 'BZ', // Belize
            '502' => 'GT', // Guatemala
            '503' => 'SV', // El Salvador
            '504' => 'HN', // Honduras
            '505' => 'NI', // Nicaragua
            '506' => 'CR', // Costa Rica
            '507' => 'PA', // Panama
            '508' => 'PM', // Saint Pierre and Miquelon
            '509' => 'HT', // Haiti
            '510' => 'BO', // Bolivia
            '511' => 'PE', // Peru
            '512' => 'EC', // Ecuador
            '513' => 'CL', // Chile
            '514' => 'AR', // Argentina
            '515' => 'PE', // Peru,
            '855' => 'KH'
        ];
    }
}

if (!function_exists('disk_config')) {
    function disk_config(string $disk)
    {
        return [
            'driver' => 'local',
            'root' => storage_path("app/public/uploads/{$disk}"),
            'url' => env('APP_URL') . "/storage/uploads/{$disk}",
            'visibility' => 'public',
        ];
    }
}

if (! function_exists('admin_auth_scope')) {
    function admin_auth_scope(): string
    {
        if (! app()->bound('session')) {
            return 'administrator';
        }

        return (string) session('auth_user_scope', 'administrator');
    }
}

if (! function_exists('admin_is_tenant_user')) {
    function admin_is_tenant_user(): bool
    {
        return admin_auth_scope() === 'tenant';
    }
}

if (! function_exists('admin_can_manage_administrators')) {
    function admin_can_manage_administrators(): bool
    {
        return ! admin_is_tenant_user();
    }
}

if (! function_exists('admin_can_switch_tenant')) {
    function admin_can_switch_tenant(): bool
    {
        return ! admin_is_tenant_user();
    }
}

if (! function_exists('admin_auth_tenant_id')) {
    function admin_auth_tenant_id(): ?string
    {
        if (! app()->bound('session')) {
            return null;
        }

        $tenantId = (string) session('auth_tenant_id', '');

        return $tenantId !== '' ? $tenantId : null;
    }
}

if (! function_exists('admin_accessible_tenants')) {
    function admin_accessible_tenants()
    {
        if (! auth()->check()) {
            return collect();
        }

        if (admin_is_tenant_user()) {
            $tenantId = admin_auth_tenant_id();

            if (! $tenantId) {
                return collect();
            }

            return \App\Models\Tenant::query()
                ->where('id', $tenantId)
                ->where('status', 'Active')
                ->get();
        }

        return auth()->user()
            ->tenants()
            ->where('status', 'Active')
            ->orderBy('id')
            ->get();
    }
}

if (! function_exists('admin_current_tenant')) {
    function admin_current_tenant(): ?\App\Models\Tenant
    {
        $tenantOptions = admin_accessible_tenants();
        $selectedTenantId = (string) session('admin_selected_tenant_id', admin_auth_tenant_id() ?: '');

        if ($selectedTenantId === '') {
            return null;
        }

        return $tenantOptions->firstWhere('id', $selectedTenantId);
    }
}

if (! function_exists('admin_tenant_display_name')) {
    function admin_tenant_display_name(?\App\Models\Tenant $tenant): string
    {
        if (! $tenant) {
            return '';
        }

        $dbName = trim((string) ($tenant->db_name ?? ''));

        return $dbName !== '' ? $dbName : (string) $tenant->id;
    }
}

if (! function_exists('tenant_currency_query')) {
    function tenant_currency_query(): \Illuminate\Database\Eloquent\Builder
    {
        $currency = new \App\Models\Currency();
        $currentTenant = tenant() ?: admin_current_tenant();

        if ($currentTenant && filled($currentTenant->database_connection_name ?? null)) {
            $currency->setConnection($currentTenant->database_connection_name);
        }

        return $currency->newQuery();
    }
}

if (! function_exists('tenant_base_currency')) {
    function tenant_base_currency(?\App\Models\Tenant $tenantModel = null): ?\App\Models\Currency
    {
        $tenantModel = $tenantModel ?: tenant() ?: admin_current_tenant();
        $currencyCode = strtoupper(trim((string) data_get($tenantModel?->general_settings, 'currency', '')));

        if ($currencyCode === '') {
            return null;
        }

        return tenant_currency_query()
            ->where('status', 'Active')
            ->where('code', $currencyCode)
            ->first();
    }
}

if (! function_exists('currency_decimal_places')) {
    function currency_decimal_places(?\App\Models\Currency $currency = null, int $fallback = 2): int
    {
        if (! $currency) {
            return max($fallback, 0);
        }

        return max((int) ($currency->decimal_places ?? $fallback), 0);
    }
}

if (! function_exists('currency_input_step')) {
    function currency_input_step(?\App\Models\Currency $currency = null, int $fallback = 2): string
    {
        $decimalPlaces = currency_decimal_places($currency, $fallback);

        if ($decimalPlaces === 0) {
            return '1';
        }

        return '0.' . str_repeat('0', $decimalPlaces - 1) . '1';
    }
}

if (! function_exists('currency_decimal_count')) {
    function currency_decimal_count($value): int
    {
        $normalizedValue = trim((string) $value);

        if ($normalizedValue === '') {
            return 0;
        }

        if (preg_match('/^\+?\d+(?:\.(\d+))?$/', $normalizedValue, $matches) !== 1) {
            return 0;
        }

        return strlen($matches[1] ?? '');
    }
}

if (! function_exists('round_currency_amount')) {
    function round_currency_amount($amount, ?\App\Models\Currency $currency = null, int $fallback = 2): float
    {
        return round((float) $amount, currency_decimal_places($currency, $fallback));
    }
}

if (! function_exists('format_currency_amount')) {
    function format_currency_amount($amount, ?\App\Models\Currency $currency = null, bool $includeCode = true, int $fallback = 2): string
    {
        $decimalPlaces = currency_decimal_places($currency, $fallback);
        $formattedAmount = number_format(
            round_currency_amount($amount, $currency, $fallback),
            $decimalPlaces,
            '.',
            ','
        );

        $currencyCode = strtoupper(trim((string) ($currency?->code ?? '')));

        if (! $includeCode || $currencyCode === '') {
            return $formattedAmount;
        }

        return $currencyCode . ' ' . $formattedAmount;
    }
}

if (! function_exists('tenant_permission_groups')) {
    function tenant_permission_groups(): \Illuminate\Support\Collection
    {
        return collect(config('tenant_permissions.permissions', []))
            ->groupBy('group');
    }
}

if (! function_exists('admin_has_permission')) {
    function admin_has_permission(string $permission): bool
    {
        if (! auth()->check()) {
            return false;
        }

        if (! admin_is_tenant_user()) {
            return true;
        }

        $user = auth()->user();

        return $user && method_exists($user, 'hasTenantPermission')
            ? $user->hasTenantPermission($permission)
            : false;
    }
}
