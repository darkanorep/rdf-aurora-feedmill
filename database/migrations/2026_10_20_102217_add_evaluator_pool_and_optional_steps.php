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
        Schema::table('acknowledgement_settings', function (Blueprint $table) {
            if (Schema::hasColumn('acknowledgement_settings', 'requires_evaluator')) {
                $table->dropColumn('requires_evaluator');
            }
            $table->json('evaluator_ids')->nullable()->after('hierarchy');
        });

        Schema::table('approval_flow_steps', function (Blueprint $table) {
            $table->boolean('is_optional')->default(false)->after('mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('approval_flow_steps', fn (Blueprint $t) => $t->dropColumn('is_optional'));
        Schema::table('acknowledgement_settings', fn (Blueprint $t) => $t->dropColumn('evaluator_ids'));
    }
};
