<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Services\MailNotificationService;
use Tests\TestCase;

class MailNotificationServiceTest extends TestCase
{
    protected MailNotificationService $service;

    protected array $originalMailConfig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MailNotificationService();
        $this->originalMailConfig = [
            'default' => config('mail.default'),
            'host' => config('mail.mailers.smtp.host'),
            'port' => config('mail.mailers.smtp.port'),
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
        ];
    }

    protected function tearDown(): void
    {
        $this->service->restoreCentralConfiguration();
        parent::tearDown();
    }

    public function test_get_tenant_mail_settings_returns_safe_defaults(): void
    {
        $tenant = new Tenant([
            'id' => 'test-tenant',
            'alias' => 'test-alias',
        ]);

        $settings = $this->service->getTenantMailSettings($tenant);

        $this->assertFalse($settings['mail_notifications_enabled']);
        $this->assertSame('smtp', $settings['mail_mailer']);
        $this->assertSame('', $settings['mail_host']);
        $this->assertSame(1025, $settings['mail_port']);
        $this->assertSame('null', $settings['mail_encryption']);
        $this->assertSame('', $settings['mail_username']);
        $this->assertSame('', $settings['mail_password']);
        $this->assertSame('', $settings['mail_from_address']);
        $this->assertSame('test-tenant', $settings['mail_from_name']);
    }

    public function test_get_tenant_mail_settings_reads_from_database_general_settings(): void
    {
        $tenant = new Tenant([
            'id' => 'rechna',
            'alias' => 'rechna',
        ]);

        $tenant->general_settings = [
            'store_name' => 'Rechna Store',
            'mail_notifications_enabled' => true,
            'mail_mailer' => 'smtp',
            'mail_host' => 'mailpit',
            'mail_port' => 1025,
            'mail_username' => 'rechna_user',
            'mail_password' => 'secret_pass',
            'mail_encryption' => 'null',
            'mail_from_address' => 'hello@rechna.com',
            'mail_from_name' => 'Rechna Official',
            'mail_order_notifications_enabled' => true,
        ];

        $settings = $this->service->getTenantMailSettings($tenant);

        $this->assertTrue($settings['mail_notifications_enabled']);
        $this->assertSame('smtp', $settings['mail_mailer']);
        $this->assertSame('mailpit', $settings['mail_host']);
        $this->assertSame(1025, $settings['mail_port']);
        $this->assertSame('rechna_user', $settings['mail_username']);
        $this->assertSame('secret_pass', $settings['mail_password']);
        $this->assertSame('null', $settings['mail_encryption']);
        $this->assertSame('hello@rechna.com', $settings['mail_from_address']);
        $this->assertSame('Rechna Official', $settings['mail_from_name']);
        $this->assertTrue($settings['mail_order_notifications_enabled']);
        $this->assertTrue($this->service->isConfigured($tenant));
        $this->assertTrue($this->service->isEnabled($tenant));
    }

    public function test_configure_mailer_for_tenant_dynamically_applies_database_settings(): void
    {
        $tenant = new Tenant([
            'id' => 'rechna',
            'alias' => 'rechna',
        ]);

        $tenant->general_settings = [
            'mail_notifications_enabled' => true,
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.custom-domain.com',
            'mail_port' => 587,
            'mail_username' => 'custom-user',
            'mail_password' => 'custom-pass',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'noreply@custom-domain.com',
            'mail_from_name' => 'Custom Brand',
        ];

        $this->service->configureMailerForTenant($tenant);

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.custom-domain.com', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('tls', config('mail.mailers.smtp.encryption'));
        $this->assertSame('custom-user', config('mail.mailers.smtp.username'));
        $this->assertSame('custom-pass', config('mail.mailers.smtp.password'));
        $this->assertSame('noreply@custom-domain.com', config('mail.from.address'));
        $this->assertSame('Custom Brand', config('mail.from.name'));

        // Restore central configuration
        $this->service->restoreCentralConfiguration();

        $this->assertSame($this->originalMailConfig['default'], config('mail.default'));
        $this->assertSame($this->originalMailConfig['host'], config('mail.mailers.smtp.host'));
        $this->assertSame($this->originalMailConfig['port'], config('mail.mailers.smtp.port'));
        $this->assertSame($this->originalMailConfig['from_address'], config('mail.from.address'));
        $this->assertSame($this->originalMailConfig['from_name'], config('mail.from.name'));
    }

    public function test_multiple_tenants_have_distinct_isolated_mail_configurations(): void
    {
        $tenantA = new Tenant(['id' => 'tenant-a', 'alias' => 'tenant-a']);
        $tenantA->general_settings = [
            'mail_notifications_enabled' => true,
            'mail_host' => 'smtp.tenant-a.com',
            'mail_port' => 587,
            'mail_from_address' => 'orders@tenant-a.com',
            'mail_from_name' => 'Tenant A Store',
        ];

        $tenantB = new Tenant(['id' => 'tenant-b', 'alias' => 'tenant-b']);
        $tenantB->general_settings = [
            'mail_notifications_enabled' => true,
            'mail_host' => 'mailpit',
            'mail_port' => 1025,
            'mail_from_address' => 'support@tenant-b.com',
            'mail_from_name' => 'Tenant B Store',
        ];

        // Configure Tenant A
        $this->service->configureMailerForTenant($tenantA);
        $this->assertSame('smtp.tenant-a.com', config('mail.mailers.smtp.host'));
        $this->assertSame('orders@tenant-a.com', config('mail.from.address'));
        $this->assertSame('Tenant A Store', config('mail.from.name'));

        // Switch to Tenant B
        $this->service->configureMailerForTenant($tenantB);
        $this->assertSame('mailpit', config('mail.mailers.smtp.host'));
        $this->assertSame('support@tenant-b.com', config('mail.from.address'));
        $this->assertSame('Tenant B Store', config('mail.from.name'));
    }

    public function test_send_test_email_requires_host_for_smtp(): void
    {
        $result = $this->service->sendTestEmail('shopper@example.com', [
            'mail_mailer' => 'smtp',
            'mail_host' => '',
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('SMTP Host is required', $result['message']);
    }

    public function test_send_test_email_with_log_driver_succeeds(): void
    {
        $result = $this->service->sendTestEmail('shopper@example.com', [
            'mail_mailer' => 'log',
            'mail_from_address' => 'hello@example.com',
            'mail_from_name' => 'Test Store',
        ]);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Test email successfully sent', $result['message']);
    }

    public function test_customer_verification_code_implements_should_queue(): void
    {
        $mail = new \App\Mail\CustomerVerificationCode('123456', 'My Store', 10, 'tenant-1');

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $mail);
        $this->assertSame('123456', $mail->code);
        $this->assertSame('tenant-1', $mail->tenantId);
    }

    public function test_tenant_test_mail_notification_does_not_implement_should_queue_for_instant_feedback(): void
    {
        $mail = new \App\Mail\TenantTestMailNotification('My Store', 'admin@store.com', 'smtp.gmail.com', 587);

        $this->assertNotInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $mail);
    }

    public function test_customer_verification_code_can_be_queued(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        \Illuminate\Support\Facades\Mail::to('shopper@example.com')->queue(
            new \App\Mail\CustomerVerificationCode('654321', 'Queued Store', 15, 'rechna')
        );

        \Illuminate\Support\Facades\Mail::assertQueued(\App\Mail\CustomerVerificationCode::class, function ($mail) {
            return $mail->hasTo('shopper@example.com')
                && $mail->code === '654321'
                && $mail->tenantId === 'rechna';
        });
    }
}
