<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sliders', function (Blueprint $table) {
            if (!Schema::hasColumn('sliders', 'media_type')) {
                $table->string('media_type', 32)->default('image')->after('placement');
            }
            if (!Schema::hasColumn('sliders', 'media_url')) {
                $table->text('media_url')->nullable()->after('media_type');
            }
            if (!Schema::hasColumn('sliders', 'badge')) {
                $table->string('badge', 64)->nullable()->after('media_url');
            }
            if (!Schema::hasColumn('sliders', 'badge_bg')) {
                $table->string('badge_bg', 64)->nullable()->after('badge');
            }
            if (!Schema::hasColumn('sliders', 'badge_color')) {
                $table->string('badge_color', 64)->nullable()->after('badge_bg');
            }
            if (!Schema::hasColumn('sliders', 'discount')) {
                $table->string('discount', 64)->nullable()->after('badge_color');
            }
            if (!Schema::hasColumn('sliders', 'gradient')) {
                $table->string('gradient', 255)->nullable()->after('discount');
            }
            if (!Schema::hasColumn('sliders', 'icon')) {
                $table->string('icon', 64)->nullable()->after('gradient');
            }
            if (!Schema::hasColumn('sliders', 'tag')) {
                $table->string('tag', 128)->nullable()->after('icon');
            }
            if (!Schema::hasColumn('sliders', 'metadata')) {
                $table->json('metadata')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sliders', function (Blueprint $table) {
            $columns = [
                'media_type',
                'media_url',
                'badge',
                'badge_bg',
                'badge_color',
                'discount',
                'gradient',
                'icon',
                'tag',
                'metadata',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('sliders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
