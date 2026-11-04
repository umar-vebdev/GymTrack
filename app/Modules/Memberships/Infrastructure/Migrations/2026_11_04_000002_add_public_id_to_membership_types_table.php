<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_types', function (Blueprint $table): void {
            $table->ulid('public_id')->nullable()->unique();
        });

        foreach (DB::table('membership_types')->select('id')->lazyById(500) as $row) {
            DB::table('membership_types')->where('id', $row->id)->update([
                'public_id' => strtolower((string) Str::ulid()),
            ]);
        }

        Schema::table('membership_types', function (Blueprint $table): void {
            $table->ulid('public_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('membership_types', function (Blueprint $table): void {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};
