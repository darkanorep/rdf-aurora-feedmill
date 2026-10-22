<?php

namespace App\Console\Commands;

use App\Models\ApprovalStepType;
use App\Models\Image;
use App\Models\Response;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BackfillSubmissions extends Command
{
    protected $signature = 'approvals:backfill {--dry-run}';
    protected $description = 'Create submissions and approval steps from legacy responses (run BEFORE dropping the old columns)';

    private array $anomalies = [];

    public function handle(): int
    {
        $types = ApprovalStepType::get()->keyBy('key');
        foreach (['evaluate', 'approve', 'assess'] as $key) {
            if (! $types->has($key)) {
                $this->error("Step type '{$key}' is missing. Run the ApprovalStepTypeSeeder.");
                return self::FAILURE;
            }
        }

        $dry = (bool) $this->option('dry-run');
        $sections = DB::table('sections')->pluck('name', 'id')->map(fn ($n) => strtolower((string) $n));
        $checklistSection = DB::table('checklists')->pluck('section_id', 'id');
        $flowBySection = DB::table('approval_flows')->whereNull('deleted_at')->pluck('id', 'section_id');

        $batchNos = Response::query()->whereNotNull('batch_no')->distinct()->orderBy('batch_no')->pluck('batch_no');
        $written = $skipped = 0;
        $this->info($dry ? 'DRY RUN: nothing will be written.' : 'Writing to the database.');

        foreach ($batchNos as $batchNo) {
            if (DB::table('submissions')->where('legacy_batch_no', $batchNo)->exists()) {
                $skipped++;   // already backfilled, never touch it again
                continue;
            }

            $rows = Response::where('batch_no', $batchNo)->orderBy('id')->get();
            $isSig = fn ($r) => ! is_null($r->getRawOriginal('evaluate'))
                || ! is_null($r->getRawOriginal('approve'))
                || ! is_null($r->getRawOriginal('assess'));
            $answers = $rows->reject($isSig);
            $first = $answers->first() ?? $rows->first();

            if (! $first || ! $first->checklist_id || ! $first->user_id) {
                $this->anomalies[] = "batch {$batchNo}: missing checklist or user, skipped";
                continue;
            }

            $sectionId = $checklistSection[$first->checklist_id] ?? null;
            $section = $sections[$sectionId] ?? null;
            $submitted = $rows->contains(fn ($r) => (bool) $r->is_completed);
            $images = Image::whereIn('response_id', $rows->filter($isSig)->pluck('id'))->orderBy('id')->get()->groupBy('response_id');

            $plan = [];
            foreach ([['evaluate', 1, 'evaluator_id'], ['approve', 2, 'approver_id'], ['assess', 3, 'assessor_id']] as [$key, $order, $idCol]) {
                if ($key === 'evaluate' && $section === 'birds') continue;   // birds never had an evaluator gate
                if ($key !== 'evaluate' && $section === 'pests') continue;   // pests ended at the first sign-off

                $assignee = $answers->pluck($idCol)->filter()->first() ?? $rows->pluck($idCol)->filter()->first();

                // Pests signed through the approve column and flag.
                $sigCol = ($key === 'evaluate' && $section === 'pests') ? 'approve' : $key;
                $flags = ($key === 'evaluate' && $section === 'pests') ? ['is_evaluated', 'is_approved'] : ["is_{$key}d"];
                $flags = array_map(fn ($f) => $f === 'is_assessd' ? 'is_assessed' : $f, $flags);

                $sigRow = $rows->first(fn ($r) => ! is_null($r->getRawOriginal($sigCol)));
                $signed = $sigRow || $rows->contains(fn ($r) => collect($flags)->contains(fn ($f) => (bool) $r->{$f}));

                if (! $assignee && ! $signed) continue;
                if (! $assignee) { $this->anomalies[] = "batch {$batchNo}: {$key} signed but nobody assigned, step skipped"; continue; }
                if ($signed && ! $sigRow) $this->anomalies[] = "batch {$batchNo}: {$key} done from legacy flag, no signature on record";
                if ($key === 'evaluate' && $submitted && ! $signed) $this->anomalies[] = "batch {$batchNo}: submitted, waiting on the evaluator";

                $sig = $sigRow ? (json_decode((string) $sigRow->getRawOriginal($sigCol), true) ?: []) : [];
                $plan[] = [
                    'type' => $types[$key], 'order' => $order, 'user' => (int) $assignee, 'signed' => (bool) $signed,
                    'at' => $this->ts($sigRow?->updated_at ?? $first->updated_at),
                    'name' => $sig['name'] ?? null,
                    'image' => $sigRow ? optional($images->get($sigRow->id)?->first())->url : null,
                ];
            }

            $written++;
            if ($dry) continue;

            DB::transaction(function () use ($batchNo, $first, $submitted, $plan, $flowBySection, $sectionId) {
                $now = now();
                $submissionId = DB::table('submissions')->insertGetId([
                    'legacy_batch_no' => $batchNo,
                    'approval_flow_id' => $flowBySection[$sectionId] ?? null,
                    'checklist_id' => $first->checklist_id, 'unit_id' => $first->unit_id, 'user_id' => $first->user_id,
                    'start_at' => $this->ts($first->start_at),
                    'submitted_at' => $submitted ? $this->ts($first->end_at ?? $first->updated_at) : null,
                    'good_points' => $first->getRawOriginal('good_points'), 'remarks' => $first->getRawOriginal('remarks'),
                    'notes' => $first->getRawOriginal('notes'), 'temporal_audit' => $first->getRawOriginal('temporal_audit'),
                    'created_at' => $now, 'updated_at' => $now,
                ]);

                DB::table('responses')->where('batch_no', $batchNo)->whereNull('deleted_at')->update(['submission_id' => $submissionId]);

                foreach ($plan as $p) {
                    $stepId = DB::table('submission_steps')->insertGetId([
                        'submission_id' => $submissionId, 'step_type_id' => $p['type']->id,
                        'label' => $p['type']->label, 'status_label' => $p['type']->status_label,
                        'step_order' => $p['order'], 'phase' => 'post_submit', 'mode' => 'any',
                        'status' => $p['signed'] ? 'done' : 'pending', 'completed_at' => $p['signed'] ? $p['at'] : null,
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                    DB::table('submission_step_assignees')->insert([
                        'submission_step_id' => $stepId, 'user_id' => $p['user'],
                        'decision' => $p['signed'] ? 'approved' : null, 'acted_at' => $p['signed'] ? $p['at'] : null,
                        'signature_name' => $p['name'], 'signature_image' => $p['image'],
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            });
        }

        $this->info("Batches " . ($dry ? 'to write' : 'written') . ": {$written}, already done: {$skipped}.");
        $this->info(count($this->anomalies) . ' anomaly(ies).');
        collect($this->anomalies)->take(200)->each(fn ($a) => $this->warn($a));

        return self::SUCCESS;
    }

    private function ts(mixed $v): ?string
    {
        return $v ? Carbon::parse($v)->toDateTimeString() : null;
    }
}
