<?php

namespace Tests\Unit;

use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\Tenant;
use App\Services\TelegramNotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class TelegramNotificationServiceTest extends TestCase
{
    protected TelegramNotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TelegramNotificationService();
    }

    public function test_is_enabled_respects_tenant_settings_and_fallback(): void
    {
        $tenantWithEnabled = (new Tenant())->forceFill([
            'general_settings' => ['telegram_notifications_enabled' => true],
        ]);
        $this->assertTrue($this->service->isEnabled($tenantWithEnabled));

        $tenantWithDisabled = (new Tenant())->forceFill([
            'general_settings' => ['telegram_notifications_enabled' => false],
        ]);
        $this->assertFalse($this->service->isEnabled($tenantWithDisabled));

        // Test fallback to config
        $tenantWithoutSetting = (new Tenant())->forceFill(['general_settings' => []]);
        config(['telegram.enabled' => true]);
        $this->assertTrue($this->service->isEnabled($tenantWithoutSetting));

        config(['telegram.enabled' => false]);
        $this->assertFalse($this->service->isEnabled($tenantWithoutSetting));
    }

    public function test_get_bot_token_and_chat_id_resolves_tenant_and_config(): void
    {
        $tenant = (new Tenant())->forceFill([
            'general_settings' => [
                'telegram_bot_token' => 'tenant-bot-token-123',
                'telegram_chat_id' => 'tenant-chat-id-456',
            ],
        ]);

        $this->assertSame('tenant-bot-token-123', $this->service->getBotToken($tenant));
        $this->assertSame('tenant-chat-id-456', $this->service->getChatId($tenant));

        // Fallback to global config when tenant has empty values
        $tenantEmpty = (new Tenant())->forceFill(['general_settings' => []]);
        config([
            'telegram.bot_token' => 'global-token-789',
            'telegram.chat_id' => 'global-chat-999',
        ]);

        $this->assertSame('global-token-789', $this->service->getBotToken($tenantEmpty));
        $this->assertSame('global-chat-999', $this->service->getChatId($tenantEmpty));
    }

    public function test_send_message_dispatches_http_post_to_telegram_api(): void
    {
        Http::fake([
            'https://api.telegram.org/bot12345:TEST_TOKEN/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 999],
            ], 200),
        ]);

        $result = $this->service->sendMessage(
            '<b>Hello Telegram!</b>',
            '12345:TEST_TOKEN',
            '-100123456789'
        );

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.telegram.org/bot12345:TEST_TOKEN/sendMessage'
                && $request['chat_id'] === '-100123456789'
                && $request['text'] === '<b>Hello Telegram!</b>'
                && $request['parse_mode'] === 'HTML';
        });
    }

    public function test_send_test_message_handles_success_and_failure(): void
    {
        // Success scenario
        Http::fake([
            'https://api.telegram.org/botVALID_TOKEN/sendMessage' => Http::response(['ok' => true], 200),
            'https://api.telegram.org/botINVALID_TOKEN/sendMessage' => Http::response([
                'ok' => false,
                'error_code' => 401,
                'description' => 'Unauthorized: invalid bot token',
            ], 401),
        ]);

        $successResult = $this->service->sendTestMessage('VALID_TOKEN', '12345', 'Test Store');
        $this->assertTrue($successResult['success']);
        $this->assertStringContainsString('successfully', strtolower($successResult['message']));

        $failResult = $this->service->sendTestMessage('INVALID_TOKEN', '12345', 'Test Store');
        $this->assertFalse($failResult['success']);
        $this->assertStringContainsString('Unauthorized', $failResult['message']);
    }

    public function test_send_sample_order_notification_sends_receipt_with_inline_buttons(): void
    {
        URL::forceRootUrl('https://store.example.com');

        Http::fake([
            'https://api.telegram.org/botSAMPLE_TOKEN/sendMessage' => Http::response(['ok' => true], 200),
        ]);

        $tenant = (new Tenant())->forceFill([
            'id' => 'sample-t',
            'general_settings' => [
                'currency' => 'USD',
            ],
        ]);

        $result = $this->service->sendSampleOrderNotification('SAMPLE_TOKEN', '123456', $tenant);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('sample order receipt', strtolower($result['message']));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.telegram.org/botSAMPLE_TOKEN/sendMessage'
                && str_contains($request['text'], 'INV-TEST-0042')
                && str_contains($request['text'], 'Iced Americano')
                && str_contains($request['reply_markup'], 'Order Test Verified');
        });
    }

    public function test_filter_reply_markup_removes_localhost_urls_for_telegram(): void
    {
        $localhostMarkup = [
            'inline_keyboard' => [
                [
                    ['text' => 'Local Link', 'url' => 'http://localhost:8880/admin'],
                    ['text' => 'IP Link', 'url' => 'http://127.0.0.1:8000/orders'],
                ],
            ],
        ];

        // Should return null because all buttons have localhost/internal URLs
        $this->assertNull($this->service->filterReplyMarkup($localhostMarkup));

        $publicMarkup = [
            'inline_keyboard' => [
                [
                    ['text' => 'Local Link', 'url' => 'http://localhost:8880/admin'],
                    ['text' => 'Public Link', 'url' => 'https://store.example.com/admin'],
                ],
            ],
        ];

        // Should keep only the public link
        $filtered = $this->service->filterReplyMarkup($publicMarkup);
        $this->assertNotNull($filtered);
        $this->assertCount(1, $filtered['inline_keyboard']);
        $this->assertCount(1, $filtered['inline_keyboard'][0]);
        $this->assertSame('https://store.example.com/admin', $filtered['inline_keyboard'][0][0]['url']);
    }

    public function test_format_amount_formats_khr_and_usd_currencies(): void
    {
        $this->assertSame('៛4,000', $this->service->formatAmount(4000, 'KHR'));
        $this->assertSame('$25.50', $this->service->formatAmount(25.5, 'USD'));
        $this->assertSame('-$10.00', $this->service->formatAmount(-10.0, 'USD'));
        $this->assertSame('EUR 15.75', $this->service->formatAmount(15.75, 'EUR'));
    }

    public function test_notify_pos_sale_formats_invoice_and_items(): void
    {
        Http::fake([
            'https://api.telegram.org/botTEST_TOKEN/sendMessage' => Http::response(['ok' => true], 200),
        ]);

        $tenant = (new Tenant())->forceFill([
            'id' => 'tenant-1',
            'name' => 'Cafe Angkor',
            'general_settings' => [
                'telegram_notifications_enabled' => true,
                'telegram_bot_token' => 'TEST_TOKEN',
                'telegram_chat_id' => '123456',
            ],
        ]);

        $sale = new PosSale([
            'invoice_number' => 'INV-2026-0001',
            'payment_method' => 'Cash',
            'order_type' => 'Dine-In',
            'customer_name' => 'Sophea',
            'base_currency_code' => 'USD',
            'subtotal_base' => 10.00,
            'discount_base' => 1.00,
            'tax_base' => 0.00,
            'total_base' => 9.00,
            'cash_received_base' => 10.00,
            'change_base' => 1.00,
        ]);

        $item = new PosSaleItem([
            'name' => 'Iced Latte',
            'quantity' => 2,
            'unit_price' => 5.00,
            'line_total_base' => 10.00,
            'currency_code' => 'USD',
        ]);

        // Mock items relationship using partial mock
        $saleMock = $this->getMockBuilder(PosSale::class)
            ->onlyMethods(['items'])
            ->getMock();

        $saleMock->forceFill($sale->getAttributes());

        $hasManyMock = $this->getMockBuilder(\Illuminate\Database\Eloquent\Relations\HasMany::class)
            ->disableOriginalConstructor()
            ->getMock();

        $hasManyMock->method('get')->willReturn(new Collection([$item]));
        $saleMock->method('items')->willReturn($hasManyMock);

        $sent = $this->service->notifyPosSale($saleMock, $tenant);
        $this->assertTrue($sent);

        Http::assertSent(function ($request) {
            $text = $request['text'];
            return str_contains($text, 'INV-2026-0001')
                && str_contains($text, 'Iced Latte')
                && str_contains($text, 'Total Paid:')
                && str_contains($text, '$9.00')
                && str_contains($text, 'Change Due:');
        });
    }

    public function test_notify_order_formats_order_number_customer_and_items(): void
    {
        Http::fake([
            'https://api.telegram.org/botORDER_TOKEN/sendMessage' => Http::response(['ok' => true], 200),
        ]);

        $tenant = (new Tenant())->forceFill([
            'id' => 'tenant-1',
            'name' => 'Online Store',
            'general_settings' => [
                'telegram_notifications_enabled' => true,
                'telegram_bot_token' => 'ORDER_TOKEN',
                'telegram_chat_id' => '987654',
            ],
        ]);

        $order = new \App\Models\Order([
            'order_number' => 'ORD-999',
            'payment_method' => 'KHQR',
            'delivery_method' => 'Express Delivery',
            'currency_code' => 'USD',
            'subtotal' => 20.00,
            'discount_total' => 5.00,
            'total' => 15.00,
            'note' => 'Please call upon arrival',
        ]);

        $user = new \App\Models\User(['name' => 'Dara Meas']);
        $order->setRelation('user', $user);

        $address = new \App\Models\Address([
            'address_line' => 'Street 2004',
            'city' => 'Phnom Penh',
        ]);
        $order->setRelation('address', $address);

        $item = new \App\Models\OrderItem([
            'name' => 'Burger Combo',
            'quantity' => 2,
            'line_total' => 15.00,
        ]);

        $orderMock = $this->getMockBuilder(\App\Models\Order::class)
            ->onlyMethods(['items'])
            ->getMock();

        $orderMock->forceFill($order->getAttributes());
        $orderMock->setRelation('user', $user);
        $orderMock->setRelation('address', $address);

        $hasManyMock = $this->getMockBuilder(\Illuminate\Database\Eloquent\Relations\HasMany::class)
            ->disableOriginalConstructor()
            ->getMock();

        $hasManyMock->method('get')->willReturn(new Collection([$item]));
        $orderMock->method('items')->willReturn($hasManyMock);

        $sent = $this->service->notifyOrder($orderMock, $tenant);
        $this->assertTrue($sent);

        Http::assertSent(function ($request) {
            $text = $request['text'];
            return str_contains($text, 'ORD-999')
                && str_contains($text, 'Dara Meas')
                && str_contains($text, 'Burger Combo')
                && str_contains($text, '$15.00')
                && str_contains($text, 'Street 2004')
                && str_contains($text, 'Please call upon arrival');
        });
    }

    public function test_job_handles_missing_model_and_invalid_type_safely(): void
    {
        $job = new \App\Jobs\SendTelegramOrderNotificationJob('unknown_type', 99999);
        $job->handle($this->service);

        $jobPos = new \App\Jobs\SendTelegramOrderNotificationJob('pos_sale', 99999);
        $jobPos->handle($this->service);

        $jobOrder = new \App\Jobs\SendTelegramOrderNotificationJob('order', 99999);
        $jobOrder->handle($this->service);

        $this->assertTrue(true);
    }

    public function test_is_error_log_enabled_respects_tenant_and_fallback(): void
    {
        $tenantWithEnabled = (new Tenant())->forceFill([
            'general_settings' => ['telegram_error_log_enabled' => true],
        ]);
        $this->assertTrue($this->service->isErrorLogEnabled($tenantWithEnabled));

        $tenantWithDisabled = (new Tenant())->forceFill([
            'general_settings' => ['telegram_error_log_enabled' => false],
        ]);
        $this->assertFalse($this->service->isErrorLogEnabled($tenantWithDisabled));

        // Fallback to config
        config(['telegram.error_log_enabled' => true]);
        $tenantWithoutSetting = new Tenant();
        $this->assertTrue($this->service->isErrorLogEnabled($tenantWithoutSetting));

        config(['telegram.error_log_enabled' => false]);
        $this->assertFalse($this->service->isErrorLogEnabled($tenantWithoutSetting));
    }

    public function test_get_error_log_bot_token_and_chat_id_with_fallbacks(): void
    {
        // 1. Dedicated tenant error log credentials
        $tenantDedicated = (new Tenant())->forceFill([
            'general_settings' => [
                'telegram_bot_token' => 'MAIN_BOT_TOKEN',
                'telegram_chat_id' => 'MAIN_CHAT_ID',
                'telegram_error_log_bot_token' => 'ERROR_BOT_TOKEN',
                'telegram_error_log_chat_id' => 'ERROR_CHAT_ID',
            ],
        ]);
        $this->assertSame('ERROR_BOT_TOKEN', $this->service->getErrorLogBotToken($tenantDedicated));
        $this->assertSame('ERROR_CHAT_ID', $this->service->getErrorLogChatId($tenantDedicated));

        // 2. Inherits from main tenant credentials if error log fields are blank
        $tenantInherited = (new Tenant())->forceFill([
            'general_settings' => [
                'telegram_bot_token' => 'MAIN_BOT_TOKEN',
                'telegram_chat_id' => 'MAIN_CHAT_ID',
                'telegram_error_log_bot_token' => '',
                'telegram_error_log_chat_id' => '',
            ],
        ]);
        $this->assertSame('MAIN_BOT_TOKEN', $this->service->getErrorLogBotToken($tenantInherited));
        $this->assertSame('MAIN_CHAT_ID', $this->service->getErrorLogChatId($tenantInherited));

        // 3. Fallback to config
        config([
            'telegram.error_log_bot_token' => 'CONFIG_ERR_TOKEN',
            'telegram.error_log_chat_id' => 'CONFIG_ERR_CHAT',
        ]);
        $tenantEmpty = new Tenant();
        $this->assertSame('CONFIG_ERR_TOKEN', $this->service->getErrorLogBotToken($tenantEmpty));
        $this->assertSame('CONFIG_ERR_CHAT', $this->service->getErrorLogChatId($tenantEmpty));
    }

    public function test_notify_error_record_formats_and_sends_to_telegram(): void
    {
        Http::fake([
            'https://api.telegram.org/botERROR_TOKEN/sendMessage' => Http::response(['ok' => true], 200),
        ]);

        $tenant = (new Tenant())->forceFill([
            'id' => 'tech-tenant',
            'general_settings' => [
                'store_name' => 'Tech Store',
                'telegram_error_log_bot_token' => 'ERROR_TOKEN',
                'telegram_error_log_chat_id' => '-100999888777',
            ],
        ]);

        $simulatedException = new \RuntimeException('Database connection timeout during checkout', 500);

        $record = [
            'level_name' => 'CRITICAL',
            'message' => 'Simulated critical exception in checkout flow',
            'context' => [
                'exception' => $simulatedException,
            ],
        ];

        $sent = $this->service->notifyErrorRecord($record, $tenant);
        $this->assertTrue($sent);

        Http::assertSent(function ($request) {
            $text = $request['text'];
            return $request->url() === 'https://api.telegram.org/botERROR_TOKEN/sendMessage'
                && $request['chat_id'] === '-100999888777'
                && str_contains($text, '[CRITICAL] Laravel Error Alert')
                && str_contains($text, 'tech-tenant')
                && str_contains($text, 'Simulated critical exception')
                && str_contains($text, 'TelegramNotificationServiceTest.php');
        });
    }

    public function test_send_test_error_log_handles_sample_and_ping(): void
    {
        Http::fake([
            'https://api.telegram.org/botTEST_ERR_TOKEN/sendMessage' => Http::response(['ok' => true], 200),
        ]);

        // Sample error test
        $sampleResult = $this->service->sendTestErrorLog('TEST_ERR_TOKEN', '12345', 'My Store', 'sample');
        $this->assertTrue($sampleResult['success']);
        $this->assertStringContainsString('sent successfully', strtolower($sampleResult['message']));

        // Ping error test
        $pingResult = $this->service->sendTestErrorLog('TEST_ERR_TOKEN', '12345', 'My Store', 'ping');
        $this->assertTrue($pingResult['success']);
        $this->assertStringContainsString('sent successfully', strtolower($pingResult['message']));

        Http::assertSent(function ($request) {
            return str_contains($request['text'], 'Telegram Error Logging Ping');
        });
    }

    public function test_recursion_guard_prevents_infinite_error_logging(): void
    {
        TelegramNotificationService::$isSendingErrorLog = true;

        $tenant = (new Tenant())->forceFill([
            'general_settings' => [
                'telegram_error_log_bot_token' => 'TOKEN',
                'telegram_error_log_chat_id' => 'CHAT',
            ],
        ]);

        $result = $this->service->notifyErrorRecord(['level_name' => 'ERROR', 'message' => 'Test'], $tenant);
        $this->assertFalse($result);

        TelegramNotificationService::$isSendingErrorLog = false;
    }

    public function test_resolves_credentials_from_database_when_env_config_is_blank(): void
    {
        // Clear any global env configs
        config([
            'telegram.bot_token' => null,
            'telegram.chat_id' => null,
            'telegram.error_log_bot_token' => null,
            'telegram.error_log_chat_id' => null,
        ]);

        $tenant = (new Tenant())->forceFill([
            'id' => 'db_tenant',
            'general_settings' => [
                'telegram_notifications_enabled' => true,
                'telegram_bot_token' => 'db-token-orders',
                'telegram_chat_id' => 'db-chat-orders',
                'telegram_error_log_enabled' => true,
                'telegram_error_log_bot_token' => 'db-token-errors',
                'telegram_error_log_chat_id' => 'db-chat-errors',
            ],
        ]);

        $this->assertTrue($this->service->hasTenantErrorLogConfig($tenant));
        $this->assertSame('db-token-orders', $this->service->getBotToken($tenant));
        $this->assertSame('db-chat-orders', $this->service->getChatId($tenant));
        $this->assertSame('db-token-errors', $this->service->getErrorLogBotToken($tenant));
        $this->assertSame('db-chat-errors', $this->service->getErrorLogChatId($tenant));
    }

    public function test_send_telegram_message_job_executes_and_calls_service(): void
    {
        Http::fake([
            'https://api.telegram.org/botJOB_TOKEN/sendMessage' => Http::response(['ok' => true], 200),
        ]);

        $job = new \App\Jobs\SendTelegramMessageJob('<b>Job Message</b>', 'JOB_TOKEN', 'CHAT123');
        $job->handle($this->service);

        Http::assertSent(function ($request) {
            return $request['chat_id'] === 'CHAT123' && str_contains($request['text'], 'Job Message');
        });
    }
}


