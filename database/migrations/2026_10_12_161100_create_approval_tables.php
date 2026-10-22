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
        Schema::create('approval_step_types', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();           // evaluate, approve, assess, verify...
            $t->string('label');                   // "Approval"
            $t->string('status_label');            // "For Approval" (shown while pending)
            $t->timestamps();
        });

        Schema::create('approval_flows', function (Blueprint $t) {
            $t->id();
            $t->foreignId('section_id')->constrained('sections');
            $t->foreignId('unit_id')->nullable()->constrained('units');   // null = section default
            $t->string('name')->nullable();
            $t->unsignedBigInteger('legacy_setting_id')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['section_id', 'unit_id'], 'af_section_unit_idx');
        });

        Schema::create('approval_flow_steps', function (Blueprint $t) {
            $t->id();
            $t->foreignId('approval_flow_id')->constrained()->cascadeOnDelete();
            $t->foreignId('step_type_id')->constrained('approval_step_types');
            $t->unsignedTinyInteger('step_order');                        // same order = parallel
            $t->enum('phase', ['pre_submit', 'post_submit'])->default('post_submit');
            $t->enum('mode', ['any', 'all'])->default('any');
            $t->index(['approval_flow_id', 'step_order'], 'afs_flow_order_idx');
        });

        Schema::create('approval_flow_step_assignees', function (Blueprint $t) {
            $t->id();
            $t->foreignId('approval_flow_step_id')
                ->constrained('approval_flow_steps', indexName: 'afsa_step_fk')
                ->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users', indexName: 'afsa_user_fk');
            $t->unique(['approval_flow_step_id', 'user_id'], 'afsa_step_user_unique');
        });

        Schema::create('submissions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('legacy_batch_no')->nullable()->unique();
            $t->foreignId('approval_flow_id')->nullable()->constrained();
            $t->foreignId('checklist_id')->constrained();
            $t->foreignId('unit_id')->nullable()->constrained();
            $t->foreignId('user_id')->constrained();                      // the auditor
            $t->timestamp('start_at')->nullable();
            $t->timestamp('submitted_at')->nullable();                    // null = draft
            $t->longText('good_points')->nullable();
            $t->longText('remarks')->nullable();
            $t->text('notes')->nullable();
            $t->text('temporal_audit')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['user_id', 'submitted_at'], 'sub_user_submitted_idx');
        });

        // Runtime snapshot of each step. Editing a flow later never rewrites history.
        Schema::create('submission_steps', function (Blueprint $t) {
            $t->id();
            $t->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $t->foreignId('step_type_id')->constrained('approval_step_types');
            $t->string('label');                                          // snapshot
            $t->string('status_label');                                   // snapshot
            $t->unsignedTinyInteger('step_order');
            $t->enum('phase', ['pre_submit', 'post_submit']);
            $t->enum('mode', ['any', 'all']);
            $t->enum('status', ['pending', 'done', 'rejected'])->default('pending');
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->index(['submission_id', 'step_order'], 'ss_submission_order_idx');
        });

        // One row per person who must (or may) act on a step.
        Schema::create('submission_step_assignees', function (Blueprint $t) {
            $t->id();
            $t->foreignId('submission_step_id')
                ->constrained('submission_steps', indexName: 'ssa_step_fk')
                ->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users', indexName: 'ssa_user_fk');
            $t->enum('decision', ['approved', 'rejected'])->nullable();   // null = hasn't acted
            $t->timestamp('acted_at')->nullable();
            $t->string('signature_name')->nullable();
            $t->string('signature_image')->nullable();
            $t->text('comment')->nullable();
            $t->timestamps();
            $t->unique(['submission_step_id', 'user_id'], 'ssa_step_user_unique');
            $t->index(['user_id', 'decision'], 'ssa_user_decision_idx');  // "my pending" query
        });

        Schema::table('responses', function (Blueprint $t) {
            $t->foreignId('submission_id')
                ->nullable()
                ->after('id')
                ->constrained('submissions', indexName: 'responses_submission_fk')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('responses', function (Blueprint $t) {
            $t->dropForeign('responses_submission_fk');
            $t->dropColumn('submission_id');
        });

        Schema::disableForeignKeyConstraints();

        foreach ([
                     'submission_step_assignees',
                     'submission_steps',
                     'submissions',
                     'approval_flow_step_assignees',
                     'approval_flow_steps',
                     'approval_flows',
                     'approval_step_types',
                 ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();
    }
};
