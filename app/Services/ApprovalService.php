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
            ->where(function ($query) use ($userId, $isPending) {
                $query->where(function ($query) use ($userId, $isPending) {
                    $query->where('evaluator_id', $userId); //EVALUATE
                    $isPending
                        ? $query->whereNull('is_evaluated')->whereNull('is_approved')->whereNull('is_assessed')
                        : $query->where('is_evaluated', true)->whereNull('is_approved')->whereNull('is_assessed');
                })->orWhere(function ($query) use ($userId, $isPending) {
                    $query->where('approver_id', $userId)->where('is_completed', true)->whereHas('section', function ($q) {
                        $q->where('name', '!=', 'pests');
                    }); //APPROVER
                    $isPending
                        ? $query->whereNull('is_approved')
                        : $query->where('is_approved', true)->whereNull('is_assessed');
                })->orWhere(function ($query) use ($userId, $isPending) {
                    $query->where('assessor_id', $userId)->where('is_completed', true)->whereHas('section', function ($q) {
                        $q->where('name', '!=', 'pests');
                    }); //ASSESSOR
                    $isPending
                        ? $query->where('is_approved', true)->whereNull('is_assessed')
                        : $query->where('is_assessed', true);
                });
            })
            ->useFilters()
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
