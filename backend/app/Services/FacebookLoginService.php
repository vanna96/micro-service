<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class FacebookLoginService
{
    /**
     * @return array{enabled: bool, app_id: string, app_secret: string, graph_version: string}
     */
    public function settings(?Tenant $tenant = null): array
    {
        $tenant = $tenant ?: tenant();
        $settings = $tenant && is_array($tenant->general_settings)
            ? $tenant->general_settings
            : [];

        return [
            'enabled' => ! empty($settings['facebook_login_enabled']),
            'app_id' => trim((string) ($settings['facebook_app_id'] ?? '')),
            'app_secret' => trim((string) ($settings['facebook_app_secret'] ?? '')),
            'graph_version' => $this->normalizeGraphVersion(
                (string) ($settings['facebook_graph_version'] ?? 'v24.0')
            ),
        ];
    }

    public function isConfigured(?Tenant $tenant = null): bool
    {
        $settings = $this->settings($tenant);

        return $settings['enabled']
            && $settings['app_id'] !== ''
            && $settings['app_secret'] !== '';
    }

    public function authorizationUrl(string $redirectUri, string $state, ?Tenant $tenant = null): string
    {
        $settings = $this->settings($tenant);

        if (! $this->isConfigured($tenant)) {
            throw ValidationException::withMessages([
                'facebook' => 'Facebook sign-in is not configured for this store.',
            ]);
        }

        return 'https://www.facebook.com/'.$settings['graph_version'].'/dialog/oauth?'.http_build_query([
            'client_id' => $settings['app_id'],
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'response_type' => 'code',
            'scope' => 'email,public_profile',
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
                'facebook' => 'Facebook sign-in is not configured for this store.',
            ]);
        }

        $tokenResponse = Http::asForm()
            ->acceptJson()
            ->timeout(12)
            ->get('https://graph.facebook.com/'.$settings['graph_version'].'/oauth/access_token', [
                'client_id' => $settings['app_id'],
                'client_secret' => $settings['app_secret'],
                'redirect_uri' => $redirectUri,
                'code' => $code,
            ]);

        if (! $tokenResponse->successful() || blank($tokenResponse->json('access_token'))) {
            throw ValidationException::withMessages([
                'facebook' => 'Facebook could not verify this sign-in request. Please try again.',
            ]);
        }

        $accessToken = (string) $tokenResponse->json('access_token');
        $profileResponse = Http::acceptJson()
            ->timeout(12)
            ->get('https://graph.facebook.com/'.$settings['graph_version'].'/me', [
                'fields' => 'id,name,first_name,last_name,email,picture.type(large)',
                'access_token' => $accessToken,
                'appsecret_proof' => hash_hmac('sha256', $accessToken, $settings['app_secret']),
            ]);

        if (! $profileResponse->successful() || blank($profileResponse->json('id'))) {
            throw ValidationException::withMessages([
                'facebook' => 'Facebook profile information could not be loaded. Please try again.',
            ]);
        }

        $email = mb_strtolower(trim((string) $profileResponse->json('email', '')));

        return [
            'id' => (string) $profileResponse->json('id'),
            'name' => trim((string) $profileResponse->json('name', 'Facebook Customer')),
            'first_name' => trim((string) $profileResponse->json('first_name', '')),
            'last_name' => trim((string) $profileResponse->json('last_name', '')),
            'email' => $email !== '' ? $email : null,
            'avatar_url' => filled($profileResponse->json('picture.data.url'))
                ? (string) $profileResponse->json('picture.data.url')
                : null,
        ];
    }

    private function normalizeGraphVersion(string $version): string
    {
        $version = strtolower(trim($version));

        return preg_match('/^v\d+\.\d+$/', $version) ? $version : 'v24.0';
    }
}
