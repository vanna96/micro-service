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
            $table->string('invoice_number', 64)->nullable()->unique()->after('reference');
            $table->foreignId('customer_id')->nullable()->after('invoice_number')->constrained('customers')->nullOnDelete();
            $table->string('customer_code', 64)->nullable()->after('customer_id');
            $table->string('customer_name')->nullable()->after('customer_code');
            $table->string('order_type', 32)->nullable()->after('customer_name');
            $table->string('base_currency_code', 12)->nullable()->after('order_type');
            $table->unsignedInteger('item_count')->default(0)->after('base_currency_code');
            $table->string('discount_type', 16)->nullable()->after('item_count');
            $table->decimal('discount_value', 20, 8)->default(0)->after('discount_type');
            $table->decimal('tax_percent', 12, 8)->default(0)->after('discount_value');
            $table->decimal('subtotal_base', 20, 8)->default(0)->after('tax_percent');
            $table->decimal('discount_base', 20, 8)->default(0)->after('subtotal_base');
            $table->decimal('tax_base', 20, 8)->default(0)->after('discount_base');
            $table->decimal('service_fee_base', 20, 8)->default(0)->after('tax_base');
            $table->decimal('total_base', 20, 8)->default(0)->after('service_fee_base');
            $table->string('payment_method', 32)->nullable()->after('total_base');
            $table->decimal('cash_received_base', 20, 8)->default(0)->after('payment_method');
            $table->decimal('change_base', 20, 8)->default(0)->after('cash_received_base');
            $table->timestamp('completed_at')->nullable()->index()->after('change_base');

            $table->index(['status', 'completed_at'], 'pos_sales_status_completed_at_index');
        });

        Schema::create('pos_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_sale_id')->constrained('pos_sales')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->foreignId('item_variant_id')->nullable()->constrained('item_variants')->nullOnDelete();
            $table->string('sku', 64)->nullable();
            $table->string('name');
            $table->string('uom_code', 64)->nullable();
            $table->string('uom_name')->nullable();
            $table->json('selected_options')->nullable();
            $table->decimal('quantity', 20, 8)->default(1);
            $table->string('currency_code', 12);
            $table->decimal('unit_price', 20, 8)->default(0);
            $table->decimal('exchange_rate', 20, 8)->default(1);
            $table->decimal('unit_price_base', 20, 8)->default(0);
            $table->decimal('line_subtotal_base', 20, 8)->default(0);
            $table->decimal('discount_base', 20, 8)->default(0);
            $table->decimal('tax_base', 20, 8)->default(0);
            $table->decimal('line_total_base', 20, 8)->default(0);
            $table->timestamps();

            $table->index(['item_id', 'pos_sale_id']);
            $table->index(['currency_code', 'pos_sale_id']);
        });

        Schema::create('pos_sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_sale_id')->constrained('pos_sales')->cascadeOnDelete();
            $table->string('method', 32);
            $table->string('currency_code', 12);
            $table->string('currency_symbol', 16)->nullable();
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->decimal('amount', 20, 8);
            $table->decimal('exchange_rate', 20, 8)->default(1);
            $table->decimal('amount_base', 20, 8);
            $table->timestamps();

            $table->index(['method', 'currency_code']);
            $table->index(['pos_sale_id', 'currency_code']);
        });

        $this->backfillCompletedSales();
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sale_payments');
        Schema::dropIfExists('pos_sale_items');

        Schema::table('pos_sales', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropUnique(['invoice_number']);
            $table->dropIndex('pos_sales_status_completed_at_index');
            $table->dropIndex(['completed_at']);
            $table->dropColumn([
                'invoice_number',
                'customer_id',
                'customer_code',
                'customer_name',
                'order_type',
                'base_currency_code',
                'item_count',
                'discount_type',
                'discount_value',
                'tax_percent',
                'subtotal_base',
                'discount_base',
                'tax_base',
                'service_fee_base',
                'total_base',
                'payment_method',
                'cash_received_base',
                'change_base',
                'completed_at',
            ]);
        });
    }

    private function backfillCompletedSales(): void
    {
        DB::table('pos_sales')
            ->where('status', 'completed')
            ->orderBy('id')
            ->eachById(function (object $sale): void {
                $snapshot = is_string($sale->snapshot)
                    ? json_decode($sale->snapshot, true)
                    : (array) $sale->snapshot;

                if (! is_array($snapshot)) {
                    return;
                }

                $cart = is_array($snapshot['cart'] ?? null) ? $snapshot['cart'] : [];
                $payment = is_array($snapshot['cashTender'] ?? null) ? $snapshot['cashTender'] : [];
                $baseCurrencyCode = strtoupper((string) data_get($payment, 'base_currency.code', ''));
                $completedAt = $this->databaseDate($payment['completed_at'] ?? $sale->updated_at);
                $customerId = $this->existingId('customers', data_get($snapshot, 'customer.id'));

                DB::table('pos_sales')->where('id', $sale->id)->update([
                    'invoice_number' => filled($snapshot['invoiceNumber'] ?? null) ? $snapshot['invoiceNumber'] : null,
                    'customer_id' => $customerId,
                    'customer_code' => data_get($snapshot, 'customer.code'),
                    'customer_name' => data_get($snapshot, 'customer.name'),
                    'order_type' => $snapshot['orderType'] ?? null,
                    'base_currency_code' => $baseCurrencyCode ?: null,
                    'item_count' => (int) collect($cart)->sum(fn ($line) => (float) ($line['quantity'] ?? 0)),
                    'discount_type' => $snapshot['discountType'] ?? null,
                    'discount_value' => (float) ($snapshot['discountValue'] ?? 0),
                    'tax_percent' => (float) ($snapshot['taxPercent'] ?? 0),
                    'subtotal_base' => (float) ($snapshot['subTotal'] ?? 0),
                    'discount_base' => (float) ($snapshot['discountAmount'] ?? 0),
                    'tax_base' => (float) ($snapshot['taxAmount'] ?? 0),
                    'service_fee_base' => (float) ($snapshot['serviceFee'] ?? 0),
                    'total_base' => (float) ($snapshot['totalPayable'] ?? 0),
                    'payment_method' => $snapshot['paymentMethod'] ?? ($payment['method'] ?? null),
                    'cash_received_base' => (float) ($payment['total_received_base'] ?? $payment['total_received'] ?? 0),
                    'change_base' => (float) ($payment['change_base'] ?? $payment['change'] ?? 0),
                    'completed_at' => $completedAt,
                ]);

                $this->backfillItems((int) $sale->id, $cart, $snapshot, $baseCurrencyCode, $completedAt);
                $this->backfillPayments((int) $sale->id, $payment);
            });
    }

    private function backfillItems(int $saleId, array $cart, array $snapshot, string $baseCurrencyCode, ?string $completedAt): void
    {
        $subtotal = (float) ($snapshot['subTotal'] ?? 0);
        $discount = (float) ($snapshot['discountAmount'] ?? 0);
        $tax = (float) ($snapshot['taxAmount'] ?? 0);
        $remainingDiscount = $discount;
        $remainingTax = $tax;
        $lastIndex = array_key_last($cart);

        foreach ($cart as $index => $line) {
            $product = is_array($line['product'] ?? null) ? $line['product'] : [];
            $quantity = max(0, (float) ($line['quantity'] ?? 0));
            $currencyCode = strtoupper((string) data_get($product, 'currency.code', $baseCurrencyCode));
            $currencyCode = $currencyCode ?: $baseCurrencyCode;
            $unitPrice = max(0, (float) ($line['unitPrice'] ?? 0));
            $rate = $this->historicalRate($currencyCode, $baseCurrencyCode, $completedAt);
            $unitPriceBase = $rate > 0 ? $unitPrice / $rate : 0;
            $lineSubtotal = round($unitPriceBase * $quantity, 8);
            $lineDiscount = $index === $lastIndex
                ? $remainingDiscount
                : round($subtotal > 0 ? $discount * ($lineSubtotal / $subtotal) : 0, 8);
            $lineTax = $index === $lastIndex
                ? $remainingTax
                : round($subtotal > 0 ? $tax * ($lineSubtotal / $subtotal) : 0, 8);
            $remainingDiscount -= $lineDiscount;
            $remainingTax -= $lineTax;

            DB::table('pos_sale_items')->insert([
                'pos_sale_id' => $saleId,
                'item_id' => $this->existingId('items', $product['id'] ?? null),
                'item_variant_id' => $this->existingId('item_variants', data_get($line, 'selectedVariant.id')),
                'sku' => $product['sku'] ?? null,
                'name' => (string) ($product['name'] ?? 'Unknown item'),
                'uom_code' => data_get($line, 'selectedUOM.shortCode'),
                'uom_name' => data_get($line, 'selectedUOM.name'),
                'selected_options' => filled($line['selectedVariants'] ?? null) ? json_encode($line['selectedVariants']) : null,
                'quantity' => $quantity,
                'currency_code' => $currencyCode,
                'unit_price' => $unitPrice,
                'exchange_rate' => $rate,
                'unit_price_base' => round($unitPriceBase, 8),
                'line_subtotal_base' => $lineSubtotal,
                'discount_base' => max(0, round($lineDiscount, 8)),
                'tax_base' => max(0, round($lineTax, 8)),
                'line_total_base' => max(0, round($lineSubtotal - $lineDiscount + $lineTax, 8)),
                'created_at' => $saleDate = $completedAt ?? now(),
                'updated_at' => $saleDate,
            ]);
        }
    }

    private function backfillPayments(int $saleId, array $payment): void
    {
        foreach ((array) ($payment['tenders'] ?? []) as $tender) {
            DB::table('pos_sale_payments')->insert([
                'pos_sale_id' => $saleId,
                'method' => (string) ($payment['method'] ?? 'Cash'),
                'currency_code' => strtoupper((string) ($tender['currency_code'] ?? '')),
                'currency_symbol' => $tender['currency_symbol'] ?? null,
                'decimal_places' => (int) ($tender['decimal_places'] ?? 2),
                'amount' => (float) ($tender['amount'] ?? 0),
                'exchange_rate' => (float) ($tender['exchange_rate'] ?? 1),
                'amount_base' => (float) ($tender['base_amount'] ?? 0),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function historicalRate(string $currencyCode, string $baseCurrencyCode, ?string $completedAt): float
    {
        if ($currencyCode === '' || $currencyCode === $baseCurrencyCode) {
            return 1;
        }

        $currencyId = DB::table('currencies')->whereRaw('UPPER(code) = ?', [$currencyCode])->value('id');
        if (! $currencyId || ! $completedAt) {
            return 0;
        }

        $timestamp = strtotime($completedAt);
        $rate = DB::table('rate_index_values')
            ->where('dataset_type', 'exchange_rate')
            ->where('currency_id', $currencyId)
            ->where('year', (int) date('Y', $timestamp))
            ->where('month', (int) date('n', $timestamp))
            ->where('day', (int) date('j', $timestamp))
            ->value('value');

        return max(0, (float) $rate);
    }

    private function existingId(string $table, mixed $id): ?int
    {
        if (! is_numeric($id) || (int) $id < 1) {
            return null;
        }

        return DB::table($table)->where('id', (int) $id)->exists() ? (int) $id : null;
    }

    private function databaseDate(mixed $value): ?string
    {
        if (! $value || strtotime((string) $value) === false) {
            return null;
        }

        return date('Y-m-d H:i:s', strtotime((string) $value));
    }
};
