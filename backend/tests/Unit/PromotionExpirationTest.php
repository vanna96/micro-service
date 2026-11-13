<?php

namespace Tests\Unit;

use App\Cache\CatalogCacheTags;
use App\Contracts\CatalogCacheInvalidator;
use App\Models\Promotion;
use App\Repositories\PromotionRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class PromotionExpirationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        config()->set('database.connections.central', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('query-cache.store', 'array');
        DB::purge('central');

        Schema::connection('central')->create('promotions', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type');
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->string('status')->default('Active');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        DB::purge('central');
        parent::tearDown();
    }

    public function test_only_active_promotions_ending_before_the_cutoff_are_expired(): void
    {
        $at = CarbonImmutable::parse('2026-10-12 12:00:00');
        $expired = $this->promotion('expired-active', 'Active', $at->subSecond());
        $atBoundary = $this->promotion('boundary-active', 'Active', $at);
        $future = $this->promotion('future-active', 'Active', $at->addSecond());
        $alreadyInactive = $this->promotion('expired-inactive', 'Inactive', $at->subDay());

        $invalidator = Mockery::mock(CatalogCacheInvalidator::class);
        $invalidator->shouldReceive('invalidateAfterCommit')
            ->once()
            ->with(
                [CatalogCacheTags::PROMOTIONS, CatalogCacheTags::ITEMS, CatalogCacheTags::BOOTSTRAP],
                'central',
                null
            );
        $this->app->instance(CatalogCacheInvalidator::class, $invalidator);

        $count = app(PromotionRepository::class)->expireEndedPromotions($at);

        $this->assertSame(1, $count);
        $this->assertSame('Inactive', $expired->refresh()->status);
        $this->assertSame('Active', $atBoundary->refresh()->status);
        $this->assertSame('Active', $future->refresh()->status);
        $this->assertSame('Inactive', $alreadyInactive->refresh()->status);
    }

    public function test_cache_is_not_invalidated_when_no_promotion_expires(): void
    {
        $at = CarbonImmutable::parse('2026-10-12 12:00:00');
        $this->promotion('future-active', 'Active', $at->addMinute());

        $invalidator = Mockery::mock(CatalogCacheInvalidator::class);
        $invalidator->shouldNotReceive('invalidateAfterCommit');
        $this->app->instance(CatalogCacheInvalidator::class, $invalidator);

        $this->assertSame(0, app(PromotionRepository::class)->expireEndedPromotions($at));
    }

    private function promotion(string $code, string $status, CarbonImmutable $endAt): Promotion
    {
        return Promotion::query()->create([
            'code' => $code,
            'name' => $code,
            'type' => Promotion::TYPE_ITEM_PRICE,
            'start_at' => $endAt->subDay(),
            'end_at' => $endAt,
            'status' => $status,
        ]);
    }
}
