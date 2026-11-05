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
            if (Schema::hasColumn('acknowledgement_settings', 'evaluator_ids')) {
                $table->dropColumn('evaluator_ids');
            }
        });

        Schema::table('approval_flow_steps', function (Blueprint $table) {
            if (Schema::hasColumn('approval_flow_steps', 'is_optional')) {
                $table->dropColumn('is_optional');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('acknowledgement_settings', fn (Blueprint $t) => $t->json('evaluator_ids')->nullable()->after('hierarchy'));
        Schema::table('approval_flow_steps', fn (Blueprint $t) => $t->boolean('is_optional')->default(false)->after('mode'));
    }
};
