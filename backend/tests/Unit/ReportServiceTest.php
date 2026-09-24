<?php

namespace Tests\Unit;

use App\Services\ReportService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class ReportServiceTest extends TestCase
{
    protected ReportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReportService();
    }

    public function test_resolve_date_range_today(): void
    {
        Carbon::setTestNow('2026-09-11 14:30:00');

        $range = $this->service->resolveDateRange('today');

        $this->assertSame('today', $range['preset']);
        $this->assertSame('2026-09-11 00:00:00', $range['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-11 23:59:59', $range['end']->format('Y-m-d H:i:s'));
    }

    public function test_resolve_date_range_yesterday(): void
    {
        Carbon::setTestNow('2026-09-11 14:30:00');

        $range = $this->service->resolveDateRange('yesterday');

        $this->assertSame('yesterday', $range['preset']);
        $this->assertSame('2026-09-10 00:00:00', $range['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-10 23:59:59', $range['end']->format('Y-m-d H:i:s'));
    }

    public function test_resolve_date_range_last_7_days(): void
    {
        Carbon::setTestNow('2026-09-11 14:30:00');

        $range = $this->service->resolveDateRange('last_7_days');

        $this->assertSame('last_7_days', $range['preset']);
        $this->assertSame('2026-09-05 00:00:00', $range['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-11 23:59:59', $range['end']->format('Y-m-d H:i:s'));
    }

    public function test_resolve_date_range_this_month(): void
    {
        Carbon::setTestNow('2026-09-11 14:30:00');

        $range = $this->service->resolveDateRange('this_month');

        $this->assertSame('this_month', $range['preset']);
        $this->assertSame('2026-09-01 00:00:00', $range['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-11 23:59:59', $range['end']->format('Y-m-d H:i:s'));
    }

    public function test_resolve_date_range_this_year(): void
    {
        Carbon::setTestNow('2026-09-11 14:30:00');

        $range = $this->service->resolveDateRange('this_year');

        $this->assertSame('this_year', $range['preset']);
        $this->assertSame('2026-01-01 00:00:00', $range['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-11 23:59:59', $range['end']->format('Y-m-d H:i:s'));
    }

    public function test_resolve_date_range_custom(): void
    {
        $range = $this->service->resolveDateRange('custom', '2026-08-01', '2026-08-15');

        $this->assertSame('custom', $range['preset']);
        $this->assertSame('2026-08-01 00:00:00', $range['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-15 23:59:59', $range['end']->format('Y-m-d H:i:s'));
    }

    public function test_get_company_info_defaults(): void
    {
        $company = $this->service->getCompanyInfo(null);

        $this->assertIsArray($company);
        $this->assertArrayHasKey('name', $company);
        $this->assertArrayHasKey('address', $company);
        $this->assertArrayHasKey('phone', $company);
        $this->assertArrayHasKey('email', $company);
        $this->assertArrayHasKey('receipt_footer', $company);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
