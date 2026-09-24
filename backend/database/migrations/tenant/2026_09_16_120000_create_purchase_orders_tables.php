<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_orders')) {
            Schema::create('purchase_orders', function (Blueprint $table) {
                $table->id();
                $table->string('po_number')->unique();
                $table->unsignedBigInteger('vendor_id')->nullable()->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->date('order_date')->index();
                $table->date('expected_date')->nullable();
                $table->string('status', 32)->default('Draft')->index();
                $table->unsignedBigInteger('currency_id')->nullable()->index();
                $table->string('currency_code', 3)->default('USD');
                $table->decimal('subtotal', 15, 4)->default(0);
                $table->decimal('tax_amount', 15, 4)->default(0);
                $table->decimal('shipping_amount', 15, 4)->default(0);
                $table->decimal('discount_amount', 15, 4)->default(0);
                $table->decimal('total_amount', 15, 4)->default(0);
                $table->text('notes')->nullable();
                $table->timestamp('stock_received_at')->nullable()->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();

                $table->foreign('vendor_id')->references('id')->on('customers')->nullOnDelete();
                $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
                $table->foreign('currency_id')->references('id')->on('currencies')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('purchase_order_items')) {
            Schema::create('purchase_order_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('purchase_order_id')->index();
                $table->unsignedBigInteger('item_id')->index();
                $table->unsignedBigInteger('item_variant_id')->nullable()->index();
                $table->unsignedBigInteger('uom_id')->nullable()->index();
                $table->decimal('quantity', 12, 4)->default(1);
                $table->decimal('unit_cost', 15, 4)->default(0);
                $table->decimal('subtotal', 15, 4)->default(0);
                $table->string('notes', 255)->nullable();
                $table->timestamps();

                $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->cascadeOnDelete();
                $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
                $table->foreign('item_variant_id')->references('id')->on('item_variants')->nullOnDelete();
                $table->foreign('uom_id')->references('id')->on('units_of_measure')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
