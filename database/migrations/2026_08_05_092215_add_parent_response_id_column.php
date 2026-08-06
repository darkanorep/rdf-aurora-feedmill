<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            // Self-referencing FK: traces which response this one was duplicated from.
            $table->foreignId('parent_response_id')
                ->nullable()
                ->after('id')
                ->constrained('responses')
                ->nullOnDelete();

            // Why the user chose to duplicate instead of starting fresh.
            $table->text('duplicate_reason')->nullable()->after('parent_response_id');

            // Enforces "at most one duplicate per response" at the DB level —
            // closes the race condition where two concurrent requests both pass
            // the app-level check before either row is committed.
            $table->unique('parent_response_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            $table->dropUnique(['parent_response_id']);
            $table->dropConstrainedForeignId('parent_response_id');
            $table->dropColumn('duplicate_reason');
        });
    }
};
