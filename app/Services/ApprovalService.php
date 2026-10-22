<?php

namespace App\Services;

use App\Models\Response;
use App\Models\Submission;
use App\Models\SubmissionStep;
use App\Models\SubmissionStepAssignee;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class ApprovalService
{
    public function __construct(
        private ResponseService $responseService,
        private SubmissionWorkflow $workflow,
    ) {}

    /**
     * Submission ids where the user has an open step (pending),
     * or has already signed one (history, permanent).
     */
    private function submissionIdsFor(int $userId, bool $isPending): Collection
    {
        $assignees = $isPending
            ? SubmissionStepAssignee::actionable()->where('submission_step_assignees.user_id', $userId)
            : SubmissionStepAssignee::where('user_id', $userId)->whereNotNull('decision');

        return SubmissionStep::whereIn('id', $assignees->select('submission_step_id'))
            ->pluck('submission_id')
            ->unique();
    }

    /** Batch numbers for the pending or history list. getResponses() and statusCount() both rely on this. */
    public function batchNosFor(int $userId, bool $isPending): Collection
    {
        return Submission::whereIn('id', $this->submissionIdsFor($userId, $isPending))
            ->whereNotNull('legacy_batch_no')
            ->pluck('legacy_batch_no');
    }

    public function getResponses($request)
    {
        $isPending = $request->input('status') === 'pending';

        $month = (int) ($request->month ?? Carbon::now()->month);
        $year  = (int) ($request->year ?? Carbon::now()->year);

        $batchNos = $this->batchNosFor(auth()->id(), $isPending);

        if ($batchNos->isEmpty()) {
            return collect();
        }

        // Same joins as before, in case useFilters() filters on checklist or section columns.
        $responses = Response::query()
            ->join('checklists', 'responses.checklist_id', '=', 'checklists.id')
            ->join('sections', 'checklists.section_id', '=', 'sections.id')
            ->whereIn('responses.batch_no', $batchNos)
            ->with(['section', 'images'])
            ->select('responses.*')
            ->useFilters()
            ->distinct()
            ->get();

        if ($responses->isEmpty()) {
            return collect();
        }

        // Use the section the caller asked for. Only fall back to the first batch when none was given.
        $requested = strtolower((string) $request->input('section'));

        if ($requested !== '') {
            $responses = $responses
                ->filter(fn ($r) => strtolower((string) $r->section?->name) === $requested)
                ->values();

            if ($responses->isEmpty()) {
                return collect();
            }
        }

        $sectionName = $requested !== ''
            ? $requested
            : strtolower((string) $responses->first()->section?->name);

        return match ($sectionName) {
            'pests', 'birds' => $this->responseService->formatPestAndBirdsResponses($responses, $sectionName, $month, $year),
            default => $this->responseService->formatCobsResponses($responses, $month, $year),
        };
    }

    public function statusCount(): array
    {
        $ids = $this->submissionIdsFor(auth()->id(), true);

        $sectionCounts = Submission::query()
            ->whereIn('submissions.id', $ids)
            ->join('checklists', 'checklists.id', '=', 'submissions.checklist_id')
            ->join('sections', 'sections.id', '=', 'checklists.section_id')
            ->selectRaw('sections.name as section_name, count(*) as total')
            ->groupBy('sections.name')
            ->pluck('total', 'section_name')
            ->mapWithKeys(fn ($total, $name) => [strtoupper($name ?? 'UNKNOWN') => (int) $total]);

        foreach (['COBS', 'PESTS', 'BIRDS'] as $section) {
            if (! $sectionCounts->has($section)) {
                $sectionCounts[$section] = 0;
            }
        }

        return ['pending' => ['TOTAL' => $sectionCounts->sum(), ...$sectionCounts->toArray()]];
    }

    /**
     * One action for every stage. The server finds the caller's own open step,
     * so the stage and the signer come from the data, not from the payload.
     */
    public function approveResponses(array $data): void
    {
        $batchNo = (int) $data['batch_no'];

        // Authorize BEFORE touching ImageKit, so a user with no turn can't upload anything.
        $this->workflow->assertCanAct($batchNo);

        $sig = $this->extractSignature($data);
        $uploaded = null;

        try {
            if ($sig['file']) {
                $uploaded = $this->responseService->uploadSignature($sig['file']);
            }

            $this->workflow->act($batchNo, [
                'name'  => $sig['name'],
                'image' => $uploaded['url'] ?? null,
            ]);
        } catch (\Throwable $e) {
            if ($uploaded) {
                $this->responseService->discardSignature($uploaded);   // don't orphan the file
            }
            throw $e;
        }
    }

    /**
     * Reads the existing payload shape: evaluate[0] / approve[0] / assess[0] plus *_image[0][0].
     * The image is read even when the JSON part is missing.
     */
    private function extractSignature(array $data): array
    {
        foreach (['evaluate', 'approve', 'assess'] as $field) {
            $items = $data[$field] ?? [];
            $imageGroup = request()->file("{$field}_image");

            if (! $items && ! $imageGroup) {
                continue;
            }

            $key = $items
                ? array_key_first($items)
                : (is_array($imageGroup) ? array_key_first($imageGroup) : 0);

            $raw = $items[$key] ?? null;
            $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
            $image = Arr::first(Arr::flatten(Arr::wrap($imageGroup)));

            return [
                'name' => is_array($decoded) ? ($decoded['name'] ?? null) : null,
                'file' => $image instanceof UploadedFile ? $image : null,
            ];
        }

        return ['name' => null, 'file' => null];
    }
}
