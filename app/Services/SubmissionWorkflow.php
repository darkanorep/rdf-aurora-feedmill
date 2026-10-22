<?php

namespace App\Services;

use App\Models\Response;
use App\Models\Submission;
use App\Models\SubmissionStep;
use App\Models\SubmissionStepAssignee;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SubmissionWorkflow
{
    public function __construct(private readonly ApprovalFlowResolver $resolver) {}

    /** Called when a batch is submitted: creates the submission and snapshots every step as pending. */
    public function finalize(int $batchNo, bool $requiresEvaluator = false): Submission
    {
        return DB::transaction(function () use ($batchNo, $requiresEvaluator) {
            $first = Response::where('batch_no', $batchNo)->orderBy('id')->first();
            abort_if(! $first, 404, 'Batch not found.');
            abort_if(! $first->checklist, 422, 'This batch has no valid checklist.');

            $existing = Submission::where('legacy_batch_no', $batchNo)->lockForUpdate()->first();
            abort_if(
                $existing && $existing->steps()->where('status', '!=', 'pending')->exists(),
                422,
                'This checklist is already in review and cannot be resubmitted.'
            );

            $flow = $this->resolver->resolve($first->checklist);

            if ($requiresEvaluator && ! $flow->steps->contains(fn ($s) => $s->is_optional)) {
                throw ValidationException::withMessages([
                    'requires_evaluator' => ['No evaluator is configured for this checklist. Contact the administrator.'],
                ]);
            }

            // Steps that apply to THIS submission: the evaluator is skipped unless required.
            $applicable = $flow->steps
                ->sortBy('step_order')
                ->reject(fn ($s) => $s->is_optional && ! $requiresEvaluator)
                ->values();

            // Never create a checklist that nobody can approve (or that is Done the moment it is submitted).
            if ($applicable->isEmpty()) {
                throw ValidationException::withMessages([
                    'approval' => ['No approvers are configured for this checklist. Contact the administrator.'],
                ]);
            }

            if ($applicable->contains(fn ($s) => $s->assignees->isEmpty())) {
                throw ValidationException::withMessages([
                    'approval' => ['An approval step has no assigned user. Contact the administrator.'],
                ]);
            }

            $submission = Submission::updateOrCreate(['legacy_batch_no' => $batchNo], [
                'approval_flow_id' => $flow->id,
                'checklist_id'     => $first->checklist_id,
                'unit_id'          => $first->unit_id,
                'user_id'          => $first->user_id,
                'start_at'         => $first->start_at,
                'submitted_at'     => now(),
                'good_points'      => $first->getRawOriginal('good_points'),
                'remarks'          => $first->getRawOriginal('remarks'),
                'notes'            => $first->getRawOriginal('notes'),
                'temporal_audit'   => $first->getRawOriginal('temporal_audit'),
            ]);

            $submission->steps()->delete();   // assignees cascade

            $approvalNo = 0;
            foreach ($applicable as $step) {
                $isApproval  = $step->type->key === 'approve';
                $label       = $isApproval ? 'Approval ' . (++$approvalNo) : $step->type->label;
                $statusLabel = $isApproval ? 'For Approval' : $step->type->status_label;

                $row = $submission->steps()->create([
                    'step_type_id' => $step->step_type_id,
                    'label'        => $label,
                    'status_label' => $statusLabel,
                    'step_order'   => $step->step_order,
                    'phase'        => 'post_submit',
                    'mode'         => $step->mode,
                    'status'       => 'pending',
                ]);
                $row->assignees()->createMany(
                    $step->assignees->map(fn ($a) => ['user_id' => $a->user_id])->all()
                );
            }

            DB::table('responses')->where('batch_no', $batchNo)->update(['submission_id' => $submission->id]);

            return $submission;
        });
    }

    /** Checked before any file is uploaded, so a user with no turn can't push files to ImageKit. */
    public function assertCanAct(int $batchNo): void
    {
        $submission = Submission::where('legacy_batch_no', $batchNo)->first();
        abort_if(! $submission, 404, 'Batch not found.');
        abort_if($submission->isDraft(), 422, 'This checklist has not been submitted yet.');

        $canAct = SubmissionStepAssignee::actionable()
            ->where('submission_step_assignees.user_id', auth()->id())
            ->whereHas('step', fn ($q) => $q->where('submission_id', $submission->id))
            ->exists();

        abort_unless($canAct, 403, 'You have no pending action on this checklist.');
    }

    /** The caller signs THEIR OWN open step. There is no way to act for someone else. */
    public function act(int $batchNo, array $signature = [], ?string $comment = null): Submission
    {
        return DB::transaction(function () use ($batchNo, $signature, $comment) {
            $submission = Submission::where('legacy_batch_no', $batchNo)->lockForUpdate()->first();
            abort_if(! $submission, 404, 'Batch not found.');
            abort_if($submission->isDraft(), 422, 'This checklist has not been submitted yet.');

            $row = SubmissionStepAssignee::actionable()
                ->where('submission_step_assignees.user_id', auth()->id())
                ->whereHas('step', fn ($q) => $q->where('submission_id', $submission->id))
                ->lockForUpdate()
                ->first();

            abort_unless($row, 403, 'You have no pending action on this checklist.');

            $step = SubmissionStep::lockForUpdate()->find($row->submission_step_id);
            abort_unless($step, 500, 'Step not found for this assignee.');

            $row->update([
                'decision'        => 'approved',
                'acted_at'        => now(),
                'signature_name'  => $signature['name'] ?? auth()->user()?->getFullNameAttribute(),
                'signature_image' => $signature['image'] ?? null,
                'comment'         => $comment,
            ]);

            $step->refreshStatus();

            return $submission->load('steps.type', 'steps.assignees');
        });
    }
}
