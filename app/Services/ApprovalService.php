<?php

namespace App\Services;

use App\Models\AcknowledgementSetting;
use App\Models\Response;
use App\Models\Unit;
use Carbon\Carbon;

class ApprovalService
{
    private ResponseService $responseService;

    public function __construct(ResponseService $responseService)
    {
        $this->responseService = $responseService;
    }

    /**
     * Builds the query for responses relevant to $userId across all three
     * review stages (evaluate/approve/assess), filtered by pending vs history.
     * Both getResponses() and statusCount() must use this — do not
     * re-implement this condition inline elsewhere, or the two will drift
     * out of sync again (as they did before this refactor).
     */
    private function pendingOrHistoryQuery(int $userId, bool $isPending)
    {
        return Response::query()
            ->join('checklists', 'responses.checklist_id', '=', 'checklists.id')
            ->join('sections', 'checklists.section_id', '=', 'sections.id')
            ->where(function ($query) use ($userId, $isPending) {
                $query->where(function ($query) use ($userId, $isPending) {
                    $query->where('responses.evaluator_id', $userId);
                    $isPending
                        ? $query->whereNull('responses.is_evaluated')->whereNull('responses.is_approved')->whereNull('responses.is_assessed')
                        : $query->where('responses.is_evaluated', true)->whereNull('responses.is_approved')->whereNull('responses.is_assessed');
                })->orWhere(function ($query) use ($userId, $isPending) {
                    $query->where('responses.approver_id', $userId)
                        ->where('responses.is_completed', true)
                        ->where('sections.name', '!=', 'pests');
                    $isPending
                        ? $query->whereNull('responses.is_approved')->where('responses.is_evaluated', true)
                        : $query->where('responses.is_approved', true)->whereNull('responses.is_assessed');
                })->orWhere(function ($query) use ($userId, $isPending) {
                    $query->where('responses.assessor_id', $userId)
                        ->where('responses.is_completed', true)
                        ->where('sections.name', '!=', 'pests');
                    $isPending
                        ? $query->where('responses.is_approved', true)->whereNull('responses.is_assessed')
                        : $query->where('responses.is_assessed', true)->where('responses.is_evaluated', false)->where('responses.is_approved', false);
                });
            });
    }

    public function getResponses($request)
    {
        $status = $request->input('status');
        $userId = auth()->id();
        $isPending = $status == 'pending';

        $month = $request->month ?? Carbon::now()->month;
        $year  = $request->year ?? Carbon::now()->year;

        $responses = $this->pendingOrHistoryQuery($userId, $isPending)
            ->with(['section', 'images'])
            ->select('responses.*')
            ->useFilters()
            ->distinct()
            ->get();

        if ($responses->isEmpty()) {
            return collect();
        }

        $sectionName = strtolower($responses->first()->section?->name) ?? '';

        return match ($sectionName) {
            'pests', 'birds' => $this->responseService->formatPestAndBirdsResponses($responses, $sectionName),
            default => $this->responseService->formatCobsResponses($responses, $month, $year),
        };
    }

    public function statusCount()
    {
        $userId = auth()->id();

        $sectionCounts = $this->pendingOrHistoryQuery($userId, isPending: true)
            ->select('sections.name as section_name', 'responses.batch_no')
            ->distinct()
            ->get()
            // A batch can match more than one of the three OR-branches only
            // if it's simultaneously pending under two different roles for
            // the same user — unique() by batch_no ensures it's only
            // counted once toward the total either way.
            ->unique('batch_no')
            ->groupBy(fn ($row) => strtoupper($row->section_name ?? 'UNKNOWN'))
            ->map(fn ($group) => $group->count());

        $allSections = ['COBS', 'PESTS', 'BIRDS'];
        foreach ($allSections as $section) {
            if (! $sectionCounts->has($section)) {
                $sectionCounts[$section] = 0;
            }
        }

        return [
            'pending' => [
                'TOTAL' => $sectionCounts->sum(),
                ...$sectionCounts->toArray(),
            ],
        ];
    }

    public function approveResponses(array $data) {
        $batchNo = $data['batch_no'];
        $section = $data['section'];

        $responses = Response::where('batch_no', $batchNo)->get();

        if ($responses->isEmpty()) {
            return;
        }

        $sample = $responses->first(); // only for checking status

        switch ($section) {

            case 'pests':

                if (!$sample->is_approved) {
                    Response::where('batch_no', $batchNo)->update(['is_approved' => true]);
                }
                break;
            case 'birds':

                if (!$sample->is_evaluated) {
                    Response::where('batch_no', $batchNo)
                        ->update(['is_evaluated' => true]);

                } elseif (!$sample->is_approved) {

                    Response::where('batch_no', $batchNo)
                        ->update(['is_approved' => true]);

                } elseif (!$sample->is_assessed) {

                    Response::where('batch_no', $batchNo)
                        ->update(['is_assessed' => true]);

                }
                break;

            default:
                $baseResponseData = $this->responseService->buildBaseResponseData($data);
                // Check if signatory_2 (approve) is already filled for this batch
                $signatory2Filled = Response::where('batch_no', $batchNo)
                    ->whereNotNull('approve')
                    ->exists();

                if (!empty($data['approve'] ?? [])) {
                    if ($signatory2Filled) {
                        // Redirect approve payload to assess
                        $this->responseService->processResponseBatch(
                            $data['approve'],
                            $data['approve_image'] ?? [],
                            'assess',
                            $baseResponseData,
                            $this->responseService->getImageKit()
                        );

                        Response::where('batch_no', $batchNo)->update(['is_assessed' => true]);
                    } else {
                        $this->responseService->processResponseBatch(
                            $data['approve'],
                            $data['approve_image'] ?? [],
                            'approve',
                            $baseResponseData,
                            $this->responseService->getImageKit()
                        );

                        Response::where('batch_no', $batchNo)->update(['is_approved' => true]);
                    }
                }

                if (!empty($data['assess'] ?? [])) {
                    $this->responseService->processResponseBatch(
                        $data['assess'],
                        $data['assess_image'] ?? [],
                        'assess',
                        $baseResponseData,
                        $this->responseService->getImageKit()
                    );

                    Response::where('batch_no', $batchNo)->update(['is_assessed' => true]);
                }
                break;
        }
    }
}
