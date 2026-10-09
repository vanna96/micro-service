<?php

namespace App\Http\Controllers\API\V1\Mobile\Concerns;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Tenant-local storefront customer authentication helpers.
 *
 * The historical trait name is kept to avoid a noisy controller rename, but
 * storefront identities are Customers and never staff/admin Users.
 */
trait InteractsWithMobileUsers
{
    protected function customerQuery(): Builder
    {
        return Customer::query()->customers();
    }

    protected function customersTable(): string
    {
        return 'tenant.customers';
    }

    protected function currentCustomer(Request $request): Customer
    {
        $customer = $request->user();

        abort_unless($customer instanceof Customer, 401, 'Unauthenticated');

        return $customer;
    }

    protected function findCustomerByLogin(string $login): ?Customer
    {
        foreach ($this->loginFieldCandidates($login) as $candidate) {
            $customer = $this->customerQuery()
                ->where(Arr::first(array_keys($candidate)), Arr::first($candidate))
                ->first();

            if ($customer instanceof Customer) {
                return $customer;
            }
        }

        return null;
    }

    protected function nextCustomerCode(): string
    {
        do {
            $code = 'WEB-'.Str::upper(Str::random(10));
        } while (Customer::query()->where('code', $code)->exists());

        return $code;
    }

    protected function loginFieldCandidates(string $login): array
    {
        $login = trim($login);

        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return [['email' => $login]];
        }

        $normalizedPhone = preg_replace('/\D+/', '', $login) ?: '';
        if (
            preg_match('/^\+?[0-9]{8,15}$/', $login) ||
            ($normalizedPhone !== '' && preg_match('/^[0-9]{8,15}$/', $normalizedPhone))
        ) {
            $phones = array_values(array_unique(array_filter([
                $normalizedPhone,
                ltrim($normalizedPhone, '0'),
                $login,
            ])));

            return array_map(static fn (string $phone) => ['phone' => $phone], $phones);
        }

        return [['username' => $login]];
    }

    protected function issueMobileToken(Customer $customer, Request $request): array
    {
        $accessExpiresMinutes = 60;
        $refreshExpiresDays = 30;

        $access = $customer->createToken(
            'customer-access',
            ['*'],
            now()->addMinutes($accessExpiresMinutes)
        );
        $access->accessToken->forceFill([
            'tenant_id' => null,
            'device_name' => 'storefront',
            'device_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ])->save();

        $refresh = $customer->createToken(
            'customer-refresh',
            ['issue-token'],
            now()->addDays($refreshExpiresDays)
        );
        $refresh->accessToken->forceFill([
            'tenant_id' => null,
            'device_name' => 'storefront',
            'device_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ])->save();

        $expiresIn = $accessExpiresMinutes * 60;

        return [
            'token' => $access->plainTextToken,
            'access_token' => $access->plainTextToken,
            'refresh_token' => $refresh->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => $expiresIn,
            'timeout' => $expiresIn,
            'timeout_minutes' => $accessExpiresMinutes,
        ];
    }

    protected function findPersonalAccessToken(string $plainTextToken): ?PersonalAccessToken
    {
        if (! tenant()) {
            return null;
        }

        $model = (new PersonalAccessToken)->setConnection(tenant()?->database_connection_name ?: 'tenant');

        if (! str_contains($plainTextToken, '|')) {
            return $model->newQuery()
                ->where('token', hash('sha256', $plainTextToken))
                ->first();
        }

        [$id, $secret] = explode('|', $plainTextToken, 2);
        $token = $model->newQuery()->find($id);

        if (! $token || ! hash_equals((string) $token->token, hash('sha256', $secret))) {
            return null;
        }

        return $token;
    }
}
