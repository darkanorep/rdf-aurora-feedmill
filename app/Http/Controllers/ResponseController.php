<?php

namespace App\Http\Controllers;

use App\Services\ResponseService;
use Illuminate\Http\Request;

class ResponseController extends Controller
{
    private $responseService;

    public function __construct(ResponseService $responseService)
    {
        $this->responseService = $responseService;
    }

    public function index(Request $request) {
        return $this->responseService->getResponses($request);
    }

    public function store(Request $request)
    {
        $data = $request->all();
//        $data['user_id'] = $request->user()->id;

        $response = $this->responseService->storeResponse($data);

        return response()->json([
            'message' => 'Response saved successfully.',
            'data' => $response,
        ], 201, [], JSON_UNESCAPED_SLASHES);
    }

    public function summaryReportByBatchNo(Request $request) {
        $batchNo = $request->input('batch_no');

        return $this->responseService->generateSummaryReportByBatchNo($batchNo);
    }
}
