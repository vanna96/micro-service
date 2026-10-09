<?php

namespace Tests\Unit;

use Tests\TestCase;

class TenancyConfigTest extends TestCase
{
    public function test_central_portal_host_falls_back_to_app_url_host(): void
    {
        $config = $this->loadTenancyConfig('https://Central.Example.test:8443/path', '');

        $this->assertSame('central.example.test', $config['central_portal_host']);
        $this->assertSame('central.example.test', $config['tenant_host']);
        $this->assertContains('central.example.test', $config['central_domains']);
    }

    public function test_explicit_central_portal_host_takes_precedence(): void
    {
        $config = $this->loadTenancyConfig('https://app.example.test', 'Portal.Example.test');

        $this->assertSame('portal.example.test', $config['central_portal_host']);
        $this->assertSame('portal.example.test', $config['tenant_host']);
        $this->assertContains('portal.example.test', $config['central_domains']);
        $this->assertNotContains('app.example.test', $config['central_domains']);
    }

    public function test_explicit_tenant_host_takes_precedence_over_central_host(): void
    {
        $config = $this->loadTenancyConfig(
            'https://app.example.test',
            'portal.example.test',
            'Stores.Example.test'
        );

        $this->assertSame('portal.example.test', $config['central_portal_host']);
        $this->assertSame('stores.example.test', $config['tenant_host']);
    }

    public function test_invalid_app_url_falls_back_to_localhost(): void
    {
        $config = $this->loadTenancyConfig('not-a-url', '');

        $this->assertSame('localhost', $config['central_portal_host']);
        $this->assertSame('localhost', $config['tenant_host']);
    }

    /** @return array<string, mixed> */
    private function loadTenancyConfig(string $appUrl, string $centralPortalHost, string $tenantHost = ''): array
    {
        $original = [
            'APP_URL' => [getenv('APP_URL'), $_ENV['APP_URL'] ?? null, $_SERVER['APP_URL'] ?? null],
            'CENTRAL_PORTAL_HOST' => [getenv('CENTRAL_PORTAL_HOST'), $_ENV['CENTRAL_PORTAL_HOST'] ?? null, $_SERVER['CENTRAL_PORTAL_HOST'] ?? null],
            'TENANT_HOST' => [getenv('TENANT_HOST'), $_ENV['TENANT_HOST'] ?? null, $_SERVER['TENANT_HOST'] ?? null],
        ];

        try {
            $this->setEnvironmentVariable('APP_URL', $appUrl);
            $this->setEnvironmentVariable('CENTRAL_PORTAL_HOST', $centralPortalHost);
            $this->setEnvironmentVariable('TENANT_HOST', $tenantHost);

            return require config_path('tenancy.php');
        } finally {
            foreach ($original as $name => [$processValue, $envValue, $serverValue]) {
                $processValue === false ? putenv($name) : putenv($name.'='.$processValue);
                if ($envValue === null) {
                    unset($_ENV[$name]);
                } else {
                    $_ENV[$name] = $envValue;
                }
                if ($serverValue === null) {
                    unset($_SERVER[$name]);
                } else {
                    $_SERVER[$name] = $serverValue;
                }
            }
        }
    }

    private function setEnvironmentVariable(string $name, string $value): void
    {
        putenv($name.'='.$value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}
