<?php

namespace Tests\Feature;

use App\Models\AcknowledgementSetting;
use App\Models\ApprovalStepType;
use App\Models\Submission;
use App\Models\User;
use App\Services\ApprovalFlowSync;
use App\Services\ApprovalService;
use App\Services\ResponseService;
use App\Services\SubmissionWorkflow;
use Database\Seeders\ApprovalStepTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Flow under test (section "cobs"):
 *   order 1  Approval 1  -> $approver1
 *   order 2  Approval 2  -> $approver2
 *
 * The evaluator is NOT part of the flow. At submit the auditor may attach a typed name and a
 * signature image; that is recorded as an already-done "Evaluation" step (order 0, no user).
 */
class ApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private static int $userSeq = 0;

    private int $sectionId;
    private int $checklistId;
    private int $unitId;
    private int $flowId;
    private int $batchNo = 100;

    private User $auditor;
    private User $approver1;
    private User $approver2;

    private SubmissionWorkflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ApprovalStepTypeSeeder::class);

        $now = now();
        $this->sectionId   = DB::table('sections')->insertGetId(['name' => 'cobs', 'created_at' => $now, 'updated_at' => $now]);
        $this->checklistId = $this->makeChecklist($this->sectionId);
        $this->unitId      = DB::table('units')->insertGetId(['name' => 'Unit 1', 'created_at' => $now, 'updated_at' => $now]);

        $this->auditor   = $this->makeUser('Auditor');
        $this->approver1 = $this->makeUser('Approver1');
        $this->approver2 = $this->makeUser('Approver2');

        $this->flowId = $this->makeFlow($this->sectionId, [
            [1, 'any', [$this->approver1->id]],
            [2, 'any', [$this->approver2->id]],
        ]);

        $this->makeBatch($this->batchNo, $this->checklistId);

        $this->workflow = app(SubmissionWorkflow::class);
    }

    // ------------------------------------------------------------------ helpers

    /** Builds a user from whichever columns the users table really has. */
    private function makeUser(string $first): User
    {
        $n = ++self::$userSeq;

        $candidates = [
            'first_name' => $first,
            'last_name'  => 'Test',
            'name'       => "$first Test",
            'username'   => strtolower($first) . $n,
            'email'      => strtolower($first) . $n . '@example.test',
            'password'   => Hash::make('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $row = array_filter(
            $candidates,
            fn ($value, $column) => Schema::hasColumn('users', $column),
            ARRAY_FILTER_USE_BOTH
        );

        return User::findOrFail(DB::table('users')->insertGetId($row));
    }

    private function makeChecklist(int $sectionId): int
    {
        return DB::table('checklists')->insertGetId([
            'section_id' => $sectionId, 'checklist_name' => 'Test', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function newSection(string $name): int
    {
        return DB::table('sections')->insertGetId(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param array<int, array{0:int,1:string,2:array<int,int>}> $levels [order, mode, user ids] */
    private function makeFlow(int $sectionId, array $levels): int
    {
        $typeId = ApprovalStepType::where('key', 'approve')->value('id');

        $flowId = DB::table('approval_flows')->insertGetId([
            'section_id' => $sectionId, 'name' => 'test', 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ($levels as [$order, $mode, $userIds]) {
            $stepId = DB::table('approval_flow_steps')->insertGetId([
                'approval_flow_id' => $flowId, 'step_type_id' => $typeId,
                'step_order' => $order, 'phase' => 'post_submit', 'mode' => $mode,
            ]);
            foreach ($userIds as $userId) {
                DB::table('approval_flow_step_assignees')->insert(['approval_flow_step_id' => $stepId, 'user_id' => $userId]);
            }
        }

        return $flowId;
    }

    private function makeFlowStep(int $order, int $userId): void
    {
        $stepId = DB::table('approval_flow_steps')->insertGetId([
            'approval_flow_id' => $this->flowId, 'step_type_id' => ApprovalStepType::where('key', 'approve')->value('id'),
            'step_order' => $order, 'phase' => 'post_submit', 'mode' => 'any',
        ]);
        DB::table('approval_flow_step_assignees')->insert(['approval_flow_step_id' => $stepId, 'user_id' => $userId]);
    }

    private function makeBatch(int $batchNo, int $checklistId, ?int $unitId = null): void
    {
        DB::table('responses')->insert([
            'batch_no' => $batchNo, 'checklist_id' => $checklistId, 'unit_id' => $unitId ?? $this->unitId,
            'user_id' => $this->auditor->id, 'response' => '{}', 'is_completed' => true,
            'start_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** $withEvaluator = the auditor attaches a typed evaluator name and signature at submit. */
    private function submit(bool $withEvaluator = false): Submission
    {
        $this->actingAs($this->auditor);

        $signature = $withEvaluator
            ? ['name' => 'Juan Dela Cruz', 'image' => 'https://example.test/e.png']
            : null;

        return $this->workflow->finalize($this->batchNo, $signature);
    }

    private function sign(User $user): void
    {
        $this->actingAs($user);
        $this->workflow->act($this->batchNo);
    }

    private function statusCode(callable $fn): ?int
    {
        try {
            $fn();
        } catch (HttpException $e) {
            return $e->getStatusCode();
        }

        return null;
    }

    private function current(): Submission
    {
        return Submission::where('legacy_batch_no', $this->batchNo)->firstOrFail()->load('steps.type', 'steps.assignees');
    }

    private function lists(): ApprovalService
    {
        return app(ApprovalService::class);
    }

    // ------------------------------------------------------- finalize

    public function test_finalize_without_an_evaluator_starts_at_approval_one(): void
    {
        $steps = $this->submit(false)->load('steps')->steps;

        $this->assertSame([1, 2], $steps->pluck('step_order')->all());
        $this->assertSame(['Approval 1', 'Approval 2'], $steps->pluck('label')->all());
        $this->assertSame(['pending'], $steps->pluck('status')->unique()->values()->all());
    }

    public function test_finalize_with_a_signed_evaluator_adds_a_done_step_first(): void
    {
        $steps = $this->submit(true)->load('steps')->steps;

        $this->assertSame([0, 1, 2], $steps->pluck('step_order')->all());
        $this->assertSame(['Evaluation', 'Approval 1', 'Approval 2'], $steps->pluck('label')->all());
        $this->assertSame(['done', 'pending', 'pending'], $steps->pluck('status')->all());
        $this->assertSame('pre_submit', $steps->first()->phase);
    }

    public function test_every_approval_step_shows_the_for_approval_status(): void
    {
        $this->submit(false);

        $this->assertSame('For Approval', $this->current()->status());
    }

    public function test_a_signed_evaluator_does_not_change_the_pending_status(): void
    {
        $this->submit(true);

        $this->assertSame('For Approval', $this->current()->status());
    }

    public function test_a_section_with_no_approvers_is_rejected(): void
    {
        DB::table('approval_flow_steps')->where('approval_flow_id', $this->flowId)->delete();

        $this->expectException(ValidationException::class);
        $this->submit(false);
    }

    public function test_a_step_with_no_assigned_user_is_rejected(): void
    {
        $stepId = DB::table('approval_flow_steps')->where('approval_flow_id', $this->flowId)->where('step_order', 2)->value('id');
        DB::table('approval_flow_step_assignees')->where('approval_flow_step_id', $stepId)->delete();

        $this->expectException(ValidationException::class);
        $this->submit(false);
    }

    public function test_a_section_with_no_flow_is_rejected(): void
    {
        DB::table('approval_flows')->where('id', $this->flowId)->delete();

        $this->expectException(ValidationException::class);
        $this->submit(false);
    }

    public function test_two_flows_for_one_section_are_rejected(): void
    {
        $this->makeFlow($this->sectionId, [[1, 'any', [$this->approver1->id]]]);

        $this->expectException(ValidationException::class);
        $this->submit(false);
    }

    public function test_resubmitting_before_anyone_signed_rebuilds_the_steps(): void
    {
        $this->submit(false);
        $this->submit(true);

        $this->assertCount(3, $this->current()->steps);
        $this->assertSame(1, Submission::where('legacy_batch_no', $this->batchNo)->count());
    }

    public function test_resubmitting_once_an_approver_has_signed_is_refused(): void
    {
        $this->submit(false);
        $this->sign($this->approver1);

        $this->assertSame(422, $this->statusCode(fn () => $this->submit(false)));
    }

    public function test_resubmitting_after_a_signed_evaluator_and_an_approver_is_refused(): void
    {
        $this->submit(true);
        $this->sign($this->approver1);

        $this->assertSame(422, $this->statusCode(fn () => $this->submit(true)));
    }

    // ------------------------------------------------------- the free-text evaluator

    public function test_a_free_text_evaluator_is_stored_by_name_with_no_user(): void
    {
        $this->submit(true);

        $row = DB::table('submission_step_assignees as a')
            ->join('submission_steps as s', 's.id', '=', 'a.submission_step_id')
            ->where('s.step_order', 0)
            ->first();

        $this->assertNull($row->user_id);
        $this->assertSame('Juan Dela Cruz', $row->signature_name);
        $this->assertSame('https://example.test/e.png', $row->signature_image);
        $this->assertSame('approved', $row->decision);
        $this->assertStringContainsString('captured by user ' . $this->auditor->id, $row->comment);
    }

    public function test_nobody_can_sign_the_evaluator_step_but_approval_one_is_open(): void
    {
        $this->submit(true);

        $this->assertSame(403, $this->statusCode(fn () => $this->sign($this->auditor)));
        $this->assertSame(403, $this->statusCode(fn () => $this->sign($this->approver2)));   // Approval 1 first

        $this->sign($this->approver1);
        $this->assertSame('For Approval', $this->current()->status());
    }

    public function test_the_summary_shows_the_typed_evaluator_name(): void
    {
        $this->submit(true);

        $summary = app(ResponseService::class)->generateSummaryReportByBatchNo($this->batchNo);

        $this->assertSame('Juan Dela Cruz', $summary['evaluator']);
        $this->assertNull($summary['evaluator_id']);
        $this->assertSame('Juan Dela Cruz', $summary['signatory_1']['name']);
        $this->assertSame('For Approval', $summary['status']);
    }

    // ------------------------------------------------------- acting

    public function test_walks_the_whole_chain_without_an_evaluator(): void
    {
        $this->submit(false);

        $this->sign($this->approver1);
        $this->assertSame('For Approval', $this->current()->status());

        $this->sign($this->approver2);
        $this->assertSame('Done', $this->current()->status());
    }

    public function test_walks_the_whole_chain_with_a_signed_evaluator(): void
    {
        $this->submit(true);

        $this->sign($this->approver1);
        $this->sign($this->approver2);

        $this->assertSame('Done', $this->current()->status());
    }

    public function test_approval_two_cannot_sign_before_approval_one(): void
    {
        $this->submit(false);

        $this->assertSame(403, $this->statusCode(fn () => $this->sign($this->approver2)));
    }

    public function test_an_unassigned_user_is_blocked(): void
    {
        $this->submit(false);

        $this->assertSame(403, $this->statusCode(fn () => $this->sign($this->auditor)));
    }

    public function test_a_double_click_is_rejected(): void
    {
        $this->submit(false);
        $this->sign($this->approver1);

        $this->assertSame(403, $this->statusCode(fn () => $this->sign($this->approver1)));
    }

    public function test_acting_on_a_draft_is_refused(): void
    {
        $submission = $this->submit(false);
        $submission->update(['submitted_at' => null]);

        $this->assertSame(422, $this->statusCode(fn () => $this->sign($this->approver1)));
    }

    public function test_acting_on_an_unknown_batch_returns_404(): void
    {
        $this->actingAs($this->approver1);

        $this->assertSame(404, $this->statusCode(fn () => $this->workflow->act(999)));
    }

    public function test_the_signature_name_and_image_are_stored(): void
    {
        $this->submit(false);

        $this->actingAs($this->approver1);
        $this->workflow->act($this->batchNo, ['name' => 'Test Signer', 'image' => 'https://example.test/sig.png'], 'ok');

        $row = DB::table('submission_step_assignees')->where('user_id', $this->approver1->id)->first();

        $this->assertSame('approved', $row->decision);
        $this->assertSame('Test Signer', $row->signature_name);
        $this->assertSame('https://example.test/sig.png', $row->signature_image);
        $this->assertNotNull($row->acted_at);
    }

    public function test_without_a_name_the_signer_full_name_is_used(): void
    {
        $this->submit(false);
        $this->sign($this->approver1);

        $row = DB::table('submission_step_assignees')->where('user_id', $this->approver1->id)->first();

        $this->assertSame($this->approver1->getFullNameAttribute(), $row->signature_name);
    }

    public function test_assert_can_act_passes_for_the_current_signer_and_blocks_others(): void
    {
        $this->submit(false);

        $this->actingAs($this->approver1);
        $this->workflow->assertCanAct($this->batchNo);   // no exception
        $this->assertTrue(true);

        $this->actingAs($this->approver2);
        $this->assertSame(403, $this->statusCode(fn () => $this->workflow->assertCanAct($this->batchNo)));
    }

    // ------------------------------------------------------- modes and numbering

    public function test_any_mode_completes_when_one_assignee_signs(): void
    {
        $second = $this->makeUser('Second');
        $stepId = DB::table('approval_flow_steps')->where('approval_flow_id', $this->flowId)->where('step_order', 1)->value('id');
        DB::table('approval_flow_step_assignees')->insert(['approval_flow_step_id' => $stepId, 'user_id' => $second->id]);

        $this->submit(false);
        $this->sign($second);   // either approver is enough

        $this->assertSame('done', $this->current()->steps->firstWhere('step_order', 1)->status);
        $this->assertSame(403, $this->statusCode(fn () => $this->sign($this->approver1)));   // nothing left for them
        $this->assertEquals([$this->batchNo], $this->lists()->batchNosFor($this->approver2->id, true)->all());
    }

    public function test_all_mode_needs_every_assignee(): void
    {
        $second = $this->makeUser('Second');
        $stepId = DB::table('approval_flow_steps')->where('approval_flow_id', $this->flowId)->where('step_order', 1)->value('id');
        DB::table('approval_flow_steps')->where('id', $stepId)->update(['mode' => 'all']);
        DB::table('approval_flow_step_assignees')->insert(['approval_flow_step_id' => $stepId, 'user_id' => $second->id]);

        $this->submit(false);

        $this->sign($this->approver1);
        $this->assertSame('pending', $this->current()->steps->firstWhere('step_order', 1)->status);
        $this->assertSame(403, $this->statusCode(fn () => $this->sign($this->approver2)));   // still blocked

        $this->sign($second);
        $this->assertSame('done', $this->current()->steps->firstWhere('step_order', 1)->status);

        $this->sign($this->approver2);
        $this->assertSame('Done', $this->current()->status());
    }

    public function test_a_fourth_approval_is_labelled_automatically(): void
    {
        $approver3 = $this->makeUser('Approver3');
        $approver4 = $this->makeUser('Approver4');
        $this->makeFlowStep(3, $approver3->id);
        $this->makeFlowStep(4, $approver4->id);

        $steps = $this->submit(false)->load('steps')->steps;

        $this->assertSame(['Approval 1', 'Approval 2', 'Approval 3', 'Approval 4'], $steps->pluck('label')->all());

        foreach ([$this->approver1, $this->approver2, $approver3, $approver4] as $user) {
            $this->sign($user);
        }

        $this->assertSame('Done', $this->current()->status());
    }

    public function test_editing_the_flow_does_not_change_a_submission_in_review(): void
    {
        $this->submit(false);

        $replacement = $this->makeUser('Replacement');
        $stepId = DB::table('approval_flow_steps')->where('approval_flow_id', $this->flowId)->where('step_order', 1)->value('id');
        DB::table('approval_flow_step_assignees')->where('approval_flow_step_id', $stepId)->update(['user_id' => $replacement->id]);

        $this->assertSame(403, $this->statusCode(fn () => $this->sign($replacement)));   // not in the snapshot
        $this->sign($this->approver1);                                                   // still the snapshotted signer
        $this->assertSame('For Approval', $this->current()->status());
    }

    // ------------------------------------------------------- pending, history, count

    public function test_pending_opens_for_one_person_at_a_time(): void
    {
        $this->submit(true);

        $this->assertEquals([$this->batchNo], $this->lists()->batchNosFor($this->approver1->id, true)->all());
        $this->assertEquals([], $this->lists()->batchNosFor($this->approver2->id, true)->all());

        $this->sign($this->approver1);

        $this->assertEquals([], $this->lists()->batchNosFor($this->approver1->id, true)->all());
        $this->assertEquals([$this->batchNo], $this->lists()->batchNosFor($this->approver2->id, true)->all());
    }

    public function test_history_is_permanent(): void
    {
        $this->submit(true);

        $this->assertEquals([], $this->lists()->batchNosFor($this->approver1->id, false)->all());

        $this->sign($this->approver1);
        $this->sign($this->approver2);

        foreach ([$this->approver1, $this->approver2] as $user) {
            $this->assertEquals([$this->batchNo], $this->lists()->batchNosFor($user->id, false)->all());
        }
    }

    public function test_the_free_text_evaluator_never_appears_in_any_list(): void
    {
        $this->submit(true);
        $this->sign($this->approver1);

        $this->assertEquals([], $this->lists()->batchNosFor($this->auditor->id, true)->all());
        $this->assertEquals([], $this->lists()->batchNosFor($this->auditor->id, false)->all());
    }

    public function test_a_draft_cannot_be_signed(): void
    {
        $submission = $this->submit(false);
        $submission->update(['submitted_at' => null]);

        $this->assertSame(422, $this->statusCode(fn () => $this->sign($this->approver1)));
    }

    public function test_status_count_counts_pending_batches_per_section(): void
    {
        $this->submit(false);

        $this->actingAs($this->approver1);
        $count = $this->lists()->statusCount();

        $this->assertSame(1, $count['pending']['TOTAL']);
        $this->assertSame(1, $count['pending']['COBS']);
        $this->assertSame(0, $count['pending']['PESTS']);
        $this->assertSame(0, $count['pending']['BIRDS']);

        $this->sign($this->approver1);

        $this->actingAs($this->approver1);
        $this->assertSame(0, $this->lists()->statusCount()['pending']['TOTAL']);
    }

    // ------------------------------------------------------- settings -> flow sync

    private function makeSetting(int $sectionId, array $hierarchy): AcknowledgementSetting
    {
        $id = DB::table('acknowledgement_settings')->insertGetId([
            'name' => 'Setting', 'section_id' => $sectionId,
            'hierarchy' => json_encode($hierarchy),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return AcknowledgementSetting::findOrFail($id);
    }

    /** @return array<int, array{0:int,1:string,2:int}> [order, type, user] */
    private function flowRows(int $sectionId): array
    {
        return DB::table('approval_flows as f')
            ->join('approval_flow_steps as s', 's.approval_flow_id', '=', 'f.id')
            ->join('approval_step_types as t', 't.id', '=', 's.step_type_id')
            ->join('approval_flow_step_assignees as a', 'a.approval_flow_step_id', '=', 's.id')
            ->where('f.section_id', $sectionId)->whereNull('f.deleted_at')
            ->orderBy('s.step_order')->orderBy('a.user_id')
            ->get(['s.step_order', 't.key', 'a.user_id'])
            ->map(fn ($r) => [(int) $r->step_order, $r->key, (int) $r->user_id])
            ->all();
    }

    public function test_sync_builds_numbered_approvals_only(): void
    {
        $section = $this->newSection('birds');
        [$u1, $u2, $u3] = [$this->makeUser('U1'), $this->makeUser('U2'), $this->makeUser('U3')];

        app(ApprovalFlowSync::class)->sync($this->makeSetting($section, [$u1->id, $u2->id, $u3->id]));

        $this->assertSame([
            [1, 'approve', $u1->id], [2, 'approve', $u2->id], [3, 'approve', $u3->id],
        ], $this->flowRows($section));
    }

    public function test_sync_turns_a_nested_list_into_one_level_everyone_must_sign(): void
    {
        $section = $this->newSection('birds');
        [$u1, $u2, $u3] = [$this->makeUser('U1'), $this->makeUser('U2'), $this->makeUser('U3')];

        app(ApprovalFlowSync::class)->sync($this->makeSetting($section, [$u1->id, [$u2->id, $u3->id]]));

        $this->assertSame([
            [1, 'approve', $u1->id], [2, 'approve', $u2->id], [2, 'approve', $u3->id],
        ], $this->flowRows($section));

        $this->assertSame(
            ['any', 'all'],
            DB::table('approval_flows as f')->join('approval_flow_steps as s', 's.approval_flow_id', '=', 'f.id')
                ->where('f.section_id', $section)->orderBy('s.step_order')->pluck('s.mode')->all()
        );
    }

    public function test_sync_applies_reordering_adding_and_removing(): void
    {
        $section = $this->newSection('birds');
        [$u1, $u2, $u3] = [$this->makeUser('U1'), $this->makeUser('U2'), $this->makeUser('U3')];
        $sync = app(ApprovalFlowSync::class);

        $setting = $this->makeSetting($section, [$u1->id, $u2->id, $u3->id]);
        $sync->sync($setting);

        // reorder and drop one: [u3, u1]
        DB::table('acknowledgement_settings')->where('id', $setting->id)->update(['hierarchy' => json_encode([$u3->id, $u1->id])]);
        $sync->sync($setting->fresh());

        $this->assertSame([[1, 'approve', $u3->id], [2, 'approve', $u1->id]], $this->flowRows($section));

        // add one back at the end
        DB::table('acknowledgement_settings')->where('id', $setting->id)->update(['hierarchy' => json_encode([$u3->id, $u1->id, $u2->id])]);
        $sync->sync($setting->fresh());

        $this->assertCount(3, $this->flowRows($section));
        $this->assertSame(1, DB::table('approval_flows')->where('section_id', $section)->count());   // one flow, not duplicated
    }

    public function test_sync_with_an_empty_hierarchy_rejects_submission(): void
    {
        $section = $this->newSection('pests');
        $checklistId = $this->makeChecklist($section);
        $this->makeBatch(200, $checklistId);

        app(ApprovalFlowSync::class)->sync($this->makeSetting($section, []));

        $this->actingAs($this->auditor);
        $this->expectException(ValidationException::class);
        $this->workflow->finalize(200);
    }

    public function test_deleting_a_setting_retires_its_flow(): void
    {
        $section = $this->newSection('birds');
        $u1 = $this->makeUser('U1');
        $setting = $this->makeSetting($section, [$u1->id]);
        $sync = app(ApprovalFlowSync::class);
        $sync->sync($setting);

        DB::table('acknowledgement_settings')->where('id', $setting->id)->update(['deleted_at' => now()]);
        $sync->sync(AcknowledgementSetting::withTrashed()->findOrFail($setting->id));

        $this->assertSame([], $this->flowRows($section));
    }

    public function test_sync_changes_do_not_affect_a_submission_already_in_review(): void
    {
        $section = $this->newSection('birds');
        $checklistId = $this->makeChecklist($section);
        $this->makeBatch(300, $checklistId);
        [$u1, $u2] = [$this->makeUser('U1'), $this->makeUser('U2')];
        $sync = app(ApprovalFlowSync::class);

        $setting = $this->makeSetting($section, [$u1->id]);
        $sync->sync($setting);

        $this->actingAs($this->auditor);
        $this->workflow->finalize(300);

        DB::table('acknowledgement_settings')->where('id', $setting->id)->update(['hierarchy' => json_encode([$u2->id])]);
        $sync->sync($setting->fresh());

        $this->actingAs($u2);
        $this->assertSame(403, $this->statusCode(fn () => $this->workflow->act(300)));   // u2 is not in the snapshot
        $this->actingAs($u1);
        $this->workflow->act(300);                                                       // u1 still is
        $this->assertTrue(true);
    }
}
