<?php

namespace App\Services;

use App\Models\Response;
use App\Models\Unit;

class ApprovalService
{
    private ResponseService $responseService;

    public function __construct(ResponseService $responseService)
    {
        $this->responseService = $responseService;
    }

//    public function getResponses($request)
//    {
//        $status = $request->input("status");
//
//        // Query the Response model directly with appropriate filters
//        $query = Response::where('approver_id', auth()->id())->where('is_completed', true);
//
//        // Apply status-based filter
//        match($status) {
//            'pending' => $query->whereNull('is_approved'),
//            default => $query->where('is_approved', true),
//        };
//
//        // Apply other filters from request and retrieve results
//        $responses = $query->useFilters()->get();
//
//        // Reuse ResponseService formatting logic
//        return $this->responseService->formatResponses($responses);
//    }

    public function getResponses($request)
    {
        $status = $request->input("status");
        $userId = auth()->id();

        // Assessor query
        $assessorQuery = Response::where('assessor_id', $userId)->where('is_completed', true);
        match($status) {
            'pending' => $assessorQuery->where('is_approved', true),
            default   => $assessorQuery->where('is_assessed', true),
        };

        // Approver query
        $approverQuery = Response::where('approver_id', $userId)->where('is_completed', true);
        match($status) {
            'pending' => $approverQuery->whereNull('is_approved'),
            default   => $approverQuery->where('is_approved', true),
        };

        // Merge both result sets, deduplicate by response id
        $responses = $assessorQuery->useFilters()->get()
            ->merge($approverQuery->useFilters()->get())
            ->unique('id');

        return $this->responseService->formatCobsResponses($responses);
    }

    public function approveResponses(array $data) {
//        $baseResponseData = $this->responseService->buildBaseResponseData($data);
//
//        $this->responseService->processResponseBatch(
//            $data['approve'] ?? [],
//            $data['approve_image'] ?? [],
//            'approve',
//            $baseResponseData,
//            $this->responseService->getImageKit()
//        );
//
//        Response::where('batch_no', $data['batch_no'])->update([
//            'is_approved' => true,
//        ]);

        $baseResponseData = $this->responseService->buildBaseResponseData($data);
        $batchNo = $data['batch_no'];

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
    }
}
