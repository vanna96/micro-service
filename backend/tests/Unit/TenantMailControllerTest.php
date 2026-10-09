<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\TenantController;
use App\Models\Tenant;
use App\Repositories\CurrencyRepository;
use App\Repositories\TenantRepository;
use App\Services\MailNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tests\TestCase;

class TenantMailControllerTest extends TestCase
{
    protected TenantController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $tenantRepo = $this->createMock(TenantRepository::class);
        $currencyRepo = $this->createMock(CurrencyRepository::class);
        $this->controller = new TenantController($tenantRepo, $currencyRepo);
    }

    public function test_test_mail_requires_valid_recipient_email(): void
    {
        $request = Request::create('/admin/tenants/test-mail', 'POST', [
            'recipient_email' => 'invalid-email',
        ]);
        $request->headers->set('Accept', 'application/json');

        $mailService = $this->createMock(MailNotificationService::class);
        $response = $this->controller->testMail($request, $mailService);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(422, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('valid recipient email address', $data['message']);
    }

    public function test_test_mail_invokes_mail_service_with_submitted_credentials(): void
    {
        $request = Request::create('/admin/tenants/test-mail', 'POST', [
            'recipient_email' => 'admin@example.com',
            'mail_mailer' => 'smtp',
            'mail_host' => 'mailpit',
            'mail_port' => '1025',
            'mail_username' => 'testuser',
            'mail_password' => 'secret123',
            'mail_encryption' => 'null',
            'mail_from_address' => 'noreply@rechna.com',
            'mail_from_name' => 'Rechna Store',
        ]);
        $request->headers->set('Accept', 'application/json');

        $mailService = $this->createMock(MailNotificationService::class);
        $mailService->expects($this->once())
            ->method('sendTestEmail')
            ->with(
                'admin@example.com',
                [
                    'mail_mailer' => 'smtp',
                    'mail_host' => 'mailpit',
                    'mail_port' => '1025',
                    'mail_username' => 'testuser',
                    'mail_password' => 'secret123',
                    'mail_encryption' => 'null',
                    'mail_from_address' => 'noreply@rechna.com',
                    'mail_from_name' => 'Rechna Store',
                ],
                null
            )
            ->willReturn([
                'success' => true,
                'message' => 'Test email successfully sent to admin@example.com via mailpit:1025!',
            ]);

        $response = $this->controller->testMail($request, $mailService);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertStringContainsString('Test email successfully sent', $data['message']);
    }
}
