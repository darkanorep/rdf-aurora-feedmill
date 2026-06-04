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

        return $this->responseService->formatResponses($responses);
    }
    public function approveResponses(array $data) {
        $baseResponseData = $this->responseService->buildBaseResponseData($data);

        $this->responseService->processResponseBatch(
            $data['approve'] ?? [],
            $data['approve_image'] ?? [],
            'approve',
            $baseResponseData,
            $this->responseService->getImageKit()
        );

        Response::where('batch_no', $data['batch_no'])->update([
            'is_approved' => true,
//            'assessor_id' => $data['assessor_id'] ?? null,
        ]);
    }
}
