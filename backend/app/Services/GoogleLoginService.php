<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class GoogleLoginService
{
    /**
     * @return array{enabled: bool, client_id: string, client_secret: string}
     */
    public function settings(?Tenant $tenant = null): array
    {
        $tenant = $tenant ?: tenant();
        $settings = $tenant && is_array($tenant->general_settings)
            ? $tenant->general_settings
            : [];

        return [
            'enabled' => ! empty($settings['google_login_enabled']),
            'client_id' => trim((string) ($settings['google_client_id'] ?? '')),
            'client_secret' => trim((string) ($settings['google_client_secret'] ?? '')),
        ];
    }

    public function isConfigured(?Tenant $tenant = null): bool
    {
        $settings = $this->settings($tenant);

        return $settings['enabled']
            && $settings['client_id'] !== ''
            && $settings['client_secret'] !== '';
    }

    public function authorizationUrl(string $redirectUri, string $state, ?Tenant $tenant = null): string
    {
        $settings = $this->settings($tenant);

        if (! $this->isConfigured($tenant)) {
            throw ValidationException::withMessages([
                'google' => 'Google sign-in is not configured for this store.',
            ]);
        }

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $settings['client_id'],
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'access_type' => 'online',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array{id: string, name: string, first_name: string, last_name: string, email: string|null, avatar_url: string|null}
     */
    public function userFromCode(string $code, string $redirectUri, ?Tenant $tenant = null): array
    {
        $settings = $this->settings($tenant);

        if (! $this->isConfigured($tenant)) {
            throw ValidationException::withMessages([
                'google' => 'Google sign-in is not configured for this store.',
            ]);
        }

        $tokenResponse = Http::asForm()
            ->acceptJson()
            ->timeout(12)
            ->post('https://oauth2.googleapis.com/token', [
                'client_id' => $settings['client_id'],
                'client_secret' => $settings['client_secret'],
                'redirect_uri' => $redirectUri,
                'code' => $code,
                'grant_type' => 'authorization_code',
            ]);

        if (! $tokenResponse->successful() || blank($tokenResponse->json('access_token'))) {
            throw ValidationException::withMessages([
                'google' => 'Google could not verify this sign-in request. Please try again.',
            ]);
        }

        $accessToken = (string) $tokenResponse->json('access_token');

        $profileResponse = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(12)
            ->get('https://www.googleapis.com/oauth2/v3/userinfo');

        if (! $profileResponse->successful() || blank($profileResponse->json('sub'))) {
            throw ValidationException::withMessages([
                'google' => 'Google profile information could not be loaded. Please try again.',
            ]);
        }

        $email = mb_strtolower(trim((string) $profileResponse->json('email', '')));

        return [
            'id' => (string) $profileResponse->json('sub'),
            'name' => trim((string) $profileResponse->json('name', 'Google Customer')),
            'first_name' => trim((string) $profileResponse->json('given_name', '')),
            'last_name' => trim((string) $profileResponse->json('family_name', '')),
            'email' => $email !== '' ? $email : null,
            'avatar_url' => (string) $profileResponse->json('picture', ''),
        ];
    }
}
