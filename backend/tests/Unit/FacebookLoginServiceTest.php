<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Services\FacebookLoginService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FacebookLoginServiceTest extends TestCase
{
    public function test_authorization_url_uses_tenant_database_credentials_and_state(): void
    {
        $tenant = $this->tenantWithFacebookSettings();
        $service = new FacebookLoginService();

        $url = $service->authorizationUrl(
            'https://rechna.example.com/api/shop/auth/facebook/callback',
            str_repeat('a', 64),
            $tenant
        );

        $this->assertStringStartsWith('https://www.facebook.com/v24.0/dialog/oauth?', $url);
        $this->assertStringContainsString('client_id=tenant-app-id', $url);
        $this->assertStringContainsString('state='.str_repeat('a', 64), $url);
        $this->assertStringContainsString('scope=email%2Cpublic_profile', $url);
    }

    public function test_code_exchange_returns_normalized_facebook_profile(): void
    {
        Http::fake([
            'graph.facebook.com/v24.0/oauth/access_token*' => Http::response([
                'access_token' => 'facebook-user-token',
            ]),
            'graph.facebook.com/v24.0/me*' => Http::response([
                'id' => '123456789',
                'name' => 'Test Shopper',
                'first_name' => 'Test',
                'last_name' => 'Shopper',
                'email' => 'SHOPPER@EXAMPLE.COM',
                'picture' => ['data' => ['url' => 'https://example.com/avatar.jpg']],
            ]),
        ]);

        $service = new FacebookLoginService();
        $profile = $service->userFromCode(
            'single-use-code',
            'https://rechna.example.com/api/shop/auth/facebook/callback',
            $this->tenantWithFacebookSettings()
        );

        $this->assertSame('123456789', $profile['id']);
        $this->assertSame('shopper@example.com', $profile['email']);
        $this->assertSame('https://example.com/avatar.jpg', $profile['avatar_url']);

        Http::assertSent(fn (Request $request) =>
            str_contains($request->url(), '/me?')
            && $request['appsecret_proof'] === hash_hmac('sha256', 'facebook-user-token', 'tenant-app-secret')
        );
    }

    private function tenantWithFacebookSettings(): Tenant
    {
        $tenant = new Tenant(['id' => 'rechna', 'alias' => 'rechna']);
        $tenant->general_settings = [
            'facebook_login_enabled' => true,
            'facebook_app_id' => 'tenant-app-id',
            'facebook_app_secret' => 'tenant-app-secret',
            'facebook_graph_version' => 'v24.0',
        ];

        return $tenant;
    }
}
