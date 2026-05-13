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

    public function getResponses($request)
    {
        $status = $request->input("status");

        // Query the Response model directly with appropriate filters
        $query = Response::
//        where('approver_id', auth()->id())
        where('is_completed', true);

        // Apply status-based filter
        match($status) {
            'pending' => $query->whereNull('is_approved'),
            default => $query->where('is_approved', true),
        };

        // Apply other filters from request and retrieve results
        $responses = $query->useFilters()->get();

        // Reuse ResponseService formatting logic
        return $this->responseService->formatResponses($responses);
    }
    public function approveResponses($batchNo): void
    {
        Response::where('batch_no', $batchNo)->update(['is_approved' => true]);
    }
}
