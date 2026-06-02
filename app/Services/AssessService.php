<?php

namespace App\Services;

use App\Models\Response;

class AssessService
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
        $query = Response::where('assessor_id', auth()->id())->where('is_completed', true);

        // Apply status-based filter
        match($status) {
            'pending' => $query->where([
                'is_approved' => true
            ]),
            default => $query->where('is_assessed', true),
        };

        // Apply other filters from request and retrieve results
        $responses = $query->useFilters()->get();

        // Reuse ResponseService formatting logic
        return $this->responseService->formatResponses($responses);
    }

    public function assessResponses(array $data) {
        $baseResponseData = $this->responseService->buildBaseResponseData($data);

        $this->responseService->processResponseBatch(
            $data['assess'] ?? [],
            $data['assess_image'] ?? [],
            'assess',
            $baseResponseData,
            $this->responseService->getImageKit()
        );

        Response::where('batch_no', $data['batch_no'])->update(['is_assessed' => true]);
    }
}
