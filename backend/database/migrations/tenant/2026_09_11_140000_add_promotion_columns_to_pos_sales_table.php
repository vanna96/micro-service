<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->foreignId('promotion_id')->nullable()->after('discount_value')->constrained('promotions')->nullOnDelete();
            $table->string('promotion_name')->nullable()->after('promotion_id');
            $table->decimal('promotion_discount_base', 20, 8)->default(0)->after('promotion_name');
        });

        // Backfill existing sales from snapshot
        $sales = DB::table('pos_sales')
            ->whereNotNull('snapshot')
            ->get(['id', 'snapshot']);

        foreach ($sales as $sale) {
            $snapshot = is_string($sale->snapshot) ? json_decode($sale->snapshot, true) : $sale->snapshot;
            if (! is_array($snapshot)) {
                continue;
            }

            $promo = $snapshot['appliedPromotion'] ?? ($snapshot['applied_promotion'] ?? null);
            if (! $promo) {
                continue;
            }

            $promoId = is_numeric($promo['id'] ?? null) ? (int) $promo['id'] : null;
            if ($promoId && ! DB::table('promotions')->where('id', $promoId)->exists()) {
                $promoId = null;
            }
            $promoName = filled($promo['name'] ?? null) ? (string) $promo['name'] : null;
            $savings = (float) ($snapshot['promotionDiscountAmount'] ?? ($promo['savings'] ?? 0));

            DB::table('pos_sales')->where('id', $sale->id)->update([
                'promotion_id' => $promoId,
                'promotion_name' => $promoName,
                'promotion_discount_base' => $savings,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->dropForeign(['promotion_id']);
            $table->dropColumn(['promotion_id', 'promotion_name', 'promotion_discount_base']);
        });
    }
};
