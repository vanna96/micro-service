<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up()
    {
        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('category_id')->constrained('branches')->nullOnDelete();
            $table->index(['branch_id', 'status']);
        });

        $branchNames = DB::table('items')
            ->whereNotNull('branch_name')
            ->where('branch_name', '!=', '')
            ->distinct()
            ->orderBy('branch_name')
            ->pluck('branch_name');

        foreach ($branchNames as $branchName) {
            $branch = DB::table('branches')->where('name', $branchName)->first();

            if (! $branch) {
                $branchId = DB::table('branches')->insertGetId([
                    'code' => $this->uniqueCodeForName($branchName),
                    'name' => $branchName,
                    'status' => 'Active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $branchId = $branch->id;
            }

            DB::table('items')
                ->where('branch_name', $branchName)
                ->whereNull('branch_id')
                ->update([
                    'branch_id' => $branchId,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down()
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'status']);
            $table->dropConstrainedForeignId('branch_id');
        });
    }

    private function uniqueCodeForName(string $name): string
    {
        $baseCode = Str::of($name)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->substr(0, 54)
            ->value();

        if ($baseCode === '') {
            $baseCode = 'branch';
        }

        $code = $baseCode;
        $suffix = 2;

        while (DB::table('branches')->where('code', $code)->exists()) {
            $code = Str::limit($baseCode, 54, '') . '-' . $suffix;
            $suffix++;
        }

        return $code;
    }
};
