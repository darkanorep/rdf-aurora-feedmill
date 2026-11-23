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
        Schema::table('submissions', function (Blueprint $table) {
            $table->timestamp('returned_at')->nullable()->after('submitted_at');
        });

        Schema::create('submission_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->string('type');                                   // rejected | resubmitted
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->unsignedTinyInteger('step_order')->nullable();
            $table->string('step_label')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['submission_id', 'created_at'], 'se_submission_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submission_events');
        Schema::table('submissions', fn (Blueprint $t) => $t->dropColumn('returned_at'));
    }
};
