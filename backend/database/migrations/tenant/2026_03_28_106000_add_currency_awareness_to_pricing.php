<?php

use App\Models\Currency;
use App\Models\Item;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('currency_id')
                ->nullable()
                ->after('price_list_id')
                ->constrained('currencies')
                ->nullOnDelete();
        });

        $this->widenMoneyColumns();
        $this->backfillItemCurrencies();
    }

    public function down(): void
    {
        $this->restoreMoneyColumns();

        Schema::table('items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('currency_id');
        });
    }

    private function widenMoneyColumns(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE items MODIFY price DECIMAL(18, 8) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE price_lists MODIFY header_fixed_price DECIMAL(18, 8) NULL');
        DB::statement('ALTER TABLE price_list_items MODIFY fixed_price DECIMAL(18, 8) NULL');
    }

    private function restoreMoneyColumns(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE items MODIFY price DECIMAL(12, 2) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE price_lists MODIFY header_fixed_price DECIMAL(12, 2) NULL');
        DB::statement('ALTER TABLE price_list_items MODIFY fixed_price DECIMAL(12, 2) NULL');
    }

    private function backfillItemCurrencies(): void
    {
        $currencyCode = strtoupper(trim((string) data_get(tenant()?->general_settings, 'currency', '')));

        if ($currencyCode === '') {
            return;
        }

        $currencyId = Currency::query()
            ->where('status', 'Active')
            ->where('code', $currencyCode)
            ->value('id');

        if (! $currencyId) {
            return;
        }

        Item::query()
            ->whereNull('currency_id')
            ->update(['currency_id' => $currencyId]);
    }
};
