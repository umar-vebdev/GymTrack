<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_bookings', function (Blueprint $table): void {
            $table->foreignId('membership_id')
                ->nullable()
                ->constrained('memberships')
                ->nullOnDelete();

            $table->index('membership_id', 'schedule_bookings_membership_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_bookings', function (Blueprint $table): void {
            $table->dropIndex('schedule_bookings_membership_id_idx');
            $table->dropConstrainedForeignId('membership_id');
        });
    }
};
