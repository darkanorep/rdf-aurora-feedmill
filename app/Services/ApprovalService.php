<?php

namespace App\Services;

use App\Models\AcknowledgementSetting;
use App\Models\Response;
use App\Models\Unit;

class ApprovalService
{
    private ResponseService $responseService;

    public function __construct(ResponseService $responseService)
    {
        $this->responseService = $responseService;
    }

    public function getResponses($request)
    {
        $status = $request->input('status');
        $userId = auth()->id();
        $isPending = $status == 'pending';

        $responses = Response::with('section')
            ->join('checklists', 'responses.checklist_id', '=', 'checklists.id')
            ->join('sections', 'checklists.section_id', '=', 'sections.id')
            ->where(function ($query) use ($userId, $isPending) {
                $query->where(function ($query) use ($userId, $isPending) {
                    $query->where('responses.evaluator_id', $userId); //EVALUATE
                    $isPending
                        ? $query->whereNull('responses.is_evaluated')->whereNull('responses.is_approved')->whereNull('responses.is_assessed')
                        : $query->where('responses.is_evaluated', true)->whereNull('responses.is_approved')->whereNull('responses.is_assessed');
                })->orWhere(function ($query) use ($userId, $isPending) {
                    $query->where('responses.approver_id', $userId)->where('responses.is_completed', true)
                        ->where('sections.name', '!=', 'pests'); //APPROVER
                    $isPending
                        ? $query->whereNull('responses.is_approved')
                        : $query->where('responses.is_approved', true)->whereNull('responses.is_assessed');
                })->orWhere(function ($query) use ($userId, $isPending) {
                    $query->where('responses.assessor_id', $userId)->where('responses.is_completed', true)
                        ->where('sections.name', '!=', 'pests'); //ASSESSOR
                    $isPending
                        ? $query->where('responses.is_approved', true)->whereNull('responses.is_assessed')
                        : $query->where('responses.is_assessed', true);
                });
            })
            ->useFilters()
            ->distinct() // Avoid duplicates from joins
            ->get();

        if ($responses->isEmpty()) {
            return collect();
        }

        $sectionName = strtolower($responses->first()->section?->name) ?? '';

        return match ($sectionName) {
            'pests', 'birds' => $this->responseService->formatPestAndBirdsResponses($responses, $sectionName),
            default => $this->responseService->formatCobsResponses($responses),
        };
    }

    public function statusCount() {
        $userId = auth()->id();

        $pendingEvaluate = Response::with('section')
            ->where('evaluator_id', $userId)
            ->whereNull('is_evaluated')
            ->whereNull('is_approved')
            ->whereNull('is_assessed')
            ->distinct('batch_no')
            ->get()
            ->groupBy('batch_no')
            ->map(fn($group) => $group->first()->section?->name)
            ->groupBy(fn($section) => strtoupper($section ?? 'UNKNOWN'))
            ->map(fn($group) => $group->count());

        $pendingApprove = Response::with('section')
            ->where('approver_id', $userId)
            ->where('is_completed', true)
            ->whereNull('is_approved')
            ->whereNull('is_assessed')
            ->distinct('batch_no')
            ->get()
            ->groupBy('batch_no')
            ->map(fn($group) => $group->first()->section?->name)
            ->groupBy(fn($section) => strtoupper($section ?? 'UNKNOWN'))
            ->map(fn($group) => $group->count());

        $pendingAssess = Response::with('section')
            ->where('assessor_id', $userId)
            ->where('is_completed', true)
            ->where('is_approved', true)
            ->whereNull('is_assessed')
            ->distinct('batch_no')
            ->get()
            ->groupBy('batch_no')
            ->map(fn($group) => $group->first()->section?->name)
            ->groupBy(fn($section) => strtoupper($section ?? 'UNKNOWN'))
            ->map(fn($group) => $group->count());

        // Merge by summing each section
        $sectionCounts = collect();

        foreach ($pendingEvaluate as $section => $count) {
            $sectionCounts[$section] = ($sectionCounts[$section] ?? 0) + $count;
        }

        foreach ($pendingApprove as $section => $count) {
            $sectionCounts[$section] = ($sectionCounts[$section] ?? 0) + $count;
        }

        foreach ($pendingAssess as $section => $count) {
            $sectionCounts[$section] = ($sectionCounts[$section] ?? 0) + $count;
        }

        // Add all sections with 0 count if not present
        $allSections = ['COBS', 'PESTS', 'BIRDS'];
        foreach ($allSections as $section) {
            if (!$sectionCounts->has($section)) {
                $sectionCounts[$section] = 0;
            }
        }

        return [
            'pending' => [
                'TOTAL' => $sectionCounts->sum(),
                ...$sectionCounts->toArray(),
            ]
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
