<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('addresses', 'customer_id')) {
            Schema::table('addresses', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->unsignedBigInteger('user_id')->nullable()->change();
                $table->foreignId('customer_id')->nullable()->after('user_id')->constrained('customers')->cascadeOnDelete();
            });
        }

        if (! Schema::hasColumn('favorites', 'customer_id')) {
            Schema::table('favorites', function (Blueprint $table) {
                // MySQL cannot drop the unique index while the user foreign key
                // still relies on its left-most column.
                $table->dropForeign(['user_id']);
                $table->dropUnique(['user_id', 'item_id']);
                $table->unsignedBigInteger('user_id')->nullable()->change();
                $table->foreignId('customer_id')->nullable()->after('user_id')->constrained('customers')->cascadeOnDelete();
                $table->unique(['customer_id', 'item_id']);
            });
        }

        if (! Schema::hasColumn('notifications', 'customer_id')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->unsignedBigInteger('user_id')->nullable()->change();
                $table->foreignId('customer_id')->nullable()->after('user_id')->constrained('customers')->cascadeOnDelete();
                $table->index(['customer_id', 'read_at']);
            });
        }

        if (! Schema::hasColumn('cart_items', 'customer_id')) {
            Schema::table('cart_items', function (Blueprint $table) {
                $table->dropUnique('cart_user_item_variant_uom_unique');
                $table->unsignedBigInteger('user_id')->nullable()->change();
                $table->foreignId('customer_id')->nullable()->after('user_id')->constrained('customers')->cascadeOnDelete();
                $table->unique(
                    ['customer_id', 'item_id', 'variant_id', 'uom_id'],
                    'cart_customer_item_variant_uom_unique'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique('cart_customer_item_variant_uom_unique');
            $table->dropConstrainedForeignId('customer_id');
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->unique(
                ['user_id', 'item_id', 'variant_id', 'uom_id'],
                'cart_user_item_variant_uom_unique'
            );
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['customer_id', 'read_at']);
            $table->dropConstrainedForeignId('customer_id');
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('favorites', function (Blueprint $table) {
            $table->dropUnique(['customer_id', 'item_id']);
            $table->dropConstrainedForeignId('customer_id');
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'item_id']);
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
