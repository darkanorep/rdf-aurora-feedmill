<?php

namespace Tests\Feature;

use App\Models\ApprovalStepType;
use App\Models\User;
use App\Services\SubmissionWorkflow;
use Database\Seeders\ApprovalStepTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regression: a history list holding batches from several sections used to be
 * formatted by whichever batch came first, so batches of the other sections vanished.
 * The caller can now ask for one section with ?section=...
 */
class ApprovalListSectionTest extends TestCase
{
    use RefreshDatabase;

    private static int $userSeq = 0;

    private User $auditor;
    private User $approver;
    private int $unitId;
    private int $cobsBatch = 501;
    private int $pestsBatch = 502;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ApprovalStepTypeSeeder::class);

        $now = now();
        $this->unitId = DB::table('units')->insertGetId(['name' => 'Unit 1', 'created_at' => $now, 'updated_at' => $now]);

        $this->auditor  = $this->makeUser('Auditor');
        $this->approver = $this->makeUser('Approver');

        // One user approves in both sections, so their lists hold both.
        $cobs  = $this->makeSection('cobs');
        $pests = $this->makeSection('pests');

        $this->makeFlow($cobs);
        $this->makeFlow($pests);

        $this->makeBatch($this->cobsBatch, $this->makeChecklist($cobs), $this->unitId);
        $this->makeBatch($this->pestsBatch, $this->makeChecklist($pests), null);
    }

    // ------------------------------------------------------------------ helpers

    private function makeUser(string $first): User
    {
        $n = ++self::$userSeq;

        $candidates = [
            'first_name' => $first, 'last_name' => 'Test', 'name' => "$first Test",
            'username' => strtolower($first) . $n, 'email' => strtolower($first) . $n . '@example.test',
            'password' => Hash::make('password'), 'created_at' => now(), 'updated_at' => now(),
        ];

        $row = array_filter(
            $candidates,
            fn ($value, $column) => Schema::hasColumn('users', $column),
            ARRAY_FILTER_USE_BOTH
        );

        return User::findOrFail(DB::table('users')->insertGetId($row));
    }

    private function makeSection(string $name): int
    {
        return DB::table('sections')->insertGetId(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function makeChecklist(int $sectionId): int
    {
        return DB::table('checklists')->insertGetId([
            'section_id' => $sectionId, 'checklist_name' => 'Checklist ' . $sectionId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makeFlow(int $sectionId): void
    {
        $flowId = DB::table('approval_flows')->insertGetId([
            'section_id' => $sectionId, 'name' => 'test', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $stepId = DB::table('approval_flow_steps')->insertGetId([
            'approval_flow_id' => $flowId, 'step_type_id' => ApprovalStepType::where('key', 'approve')->value('id'),
            'step_order' => 1, 'phase' => 'post_submit', 'mode' => 'any', 'is_optional' => false,
        ]);
        DB::table('approval_flow_step_assignees')->insert(['approval_flow_step_id' => $stepId, 'user_id' => $this->approver->id]);
    }

    private function makeBatch(int $batchNo, int $checklistId, ?int $unitId): void
    {
        DB::table('responses')->insert([
            'batch_no' => $batchNo, 'checklist_id' => $checklistId, 'unit_id' => $unitId,
            'user_id' => $this->auditor->id, 'response' => '{}', 'is_completed' => true,
            // day 3 of the current month: inside the default month, week 1 / period 1
            'start_at' => now()->startOfMonth()->addDays(2),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function submitBoth(): void
    {
        $this->actingAs($this->auditor);
        $workflow = app(SubmissionWorkflow::class);
        $workflow->finalize($this->cobsBatch, false);
        $workflow->finalize($this->pestsBatch, false);
    }

    private function signBoth(): void
    {
        $this->actingAs($this->approver);
        $workflow = app(SubmissionWorkflow::class);
        $workflow->act($this->cobsBatch);
        $workflow->act($this->pestsBatch);
    }

    private function listAs(User $user, string $query): string
    {
        Sanctum::actingAs($user);

        return $this->getJson('/api/approvals?' . $query)->assertOk()->getContent();
    }

    private function has(string $content, int $batchNo): bool
    {
        return str_contains($content, '"batch_no":' . $batchNo);
    }

    // ------------------------------------------------------------------ tests

    public function test_history_for_one_section_contains_only_that_sections_batches(): void
    {
        $this->submitBoth();
        $this->signBoth();

        $pests = $this->listAs($this->approver, 'status=history&section=pests');
        $this->assertTrue($this->has($pests, $this->pestsBatch), 'the pests batch must be listed');
        $this->assertFalse($this->has($pests, $this->cobsBatch), 'the cobs batch must not leak in');

        $cobs = $this->listAs($this->approver, 'status=history&section=cobs');
        $this->assertTrue($this->has($cobs, $this->cobsBatch), 'the cobs batch must be listed');
        $this->assertFalse($this->has($cobs, $this->pestsBatch), 'the pests batch must not leak in');
    }

    public function test_the_section_filter_is_case_insensitive(): void
    {
        $this->submitBoth();
        $this->signBoth();

        $content = $this->listAs($this->approver, 'status=history&section=PESTS');

        $this->assertTrue($this->has($content, $this->pestsBatch));
    }

    public function test_pending_for_one_section_contains_only_that_sections_batches(): void
    {
        $this->submitBoth();

        $pests = $this->listAs($this->approver, 'status=pending&section=pests');
        $this->assertTrue($this->has($pests, $this->pestsBatch));
        $this->assertFalse($this->has($pests, $this->cobsBatch));

        $cobs = $this->listAs($this->approver, 'status=pending&section=cobs');
        $this->assertTrue($this->has($cobs, $this->cobsBatch));
        $this->assertFalse($this->has($cobs, $this->pestsBatch));
    }

    public function test_an_unknown_section_returns_an_empty_list(): void
    {
        $this->submitBoth();
        $this->signBoth();

        $this->assertSame('[]', $this->listAs($this->approver, 'status=history&section=nothing'));
    }

    public function test_a_section_with_no_batches_for_the_user_returns_an_empty_list(): void
    {
        $this->submitBoth();

        // the auditor signs nothing, so there is nothing for them in any section
        $this->assertSame('[]', $this->listAs($this->auditor, 'status=pending&section=pests'));
        $this->assertSame('[]', $this->listAs($this->auditor, 'status=history&section=cobs'));
    }

    public function test_without_a_section_the_list_still_loads(): void
    {
        $this->submitBoth();
        $this->signBoth();

        // Backward compatible: no section means the first batch decides the layout.
        // The point here is only that it does not error.
        Sanctum::actingAs($this->approver);
        $this->getJson('/api/approvals?status=history')->assertOk();
    }
}
