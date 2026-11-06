<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAdditionalAttachmentRequest;
use App\Models\Submission;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

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
        $data = $request->validate([
            'checklist_id'   => ['nullable', 'integer', 'exists:checklists,id'],
            'unit_id'        => ['nullable', 'integer', 'exists:units,id'],
            'start_at'       => ['nullable', 'date'],
            'is_completed'   => ['nullable', 'boolean'],
            'batch_no'       => ['nullable', 'integer'],
            'good_points'    => ['nullable', 'string'],
            'remarks'        => ['nullable', 'string'],
            'notes'          => ['nullable', 'string'],
            'temporal_audit' => ['nullable'],            // tighten once the shape is known
            'response'       => ['nullable', 'array', 'min:1', 'max:200'],
            'response.*'     => ['nullable', 'json'],    // stricter than parseData(); see note below
            'image'          => ['sometimes', 'array'],
            'image.*'        => ['array', 'max:5'],      // max images per response
            'image.*.*'      => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'], // KB, tune this
            'requires_evaluator' => ['nullable', 'boolean'],
            'evaluator_name'      => ['nullable', 'string', 'max:150', 'required_with:evaluator_signature'],
            'evaluator_signature' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'required_with:evaluator_name'],
            'keep_image'     => ['sometimes', 'array'],
            'keep_image.*'   => ['array'],
            'keep_image.*.*' => ['integer'],
        ]);

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
    public function evaluateResponse(Request $request) {
        $data = $request->all();

        $this->responseService->evaluate($data);

        return response()->json([
            'message' => 'Response evaluate successfully.',
        ], 200);
    }
    public function assessResponse(Request $request) {
        $data = $request->all();
        $this->responseService->assess($data);
        return response()->json([
            'message' => 'Response assess successfully.',
        ], 200);
    }
    public function mergeResponse(Request $request) {
        $data = $request->all();

        $this->responseService->mergeResponse($data);

        return response()->json([
            'message' => 'Response merge successfully.',
        ]);
    }
    public function storeAdditionalAttachment(StoreAdditionalAttachmentRequest $request)
    {
        $this->responseService->additionalAttachment(
            $request->file('image'),
            $request->input('response_id')
        );

        return response()->json([
            'message' => 'Attachments uploaded successfully.',
            'response_id' => $request->input('response_id'),
        ]);
    }

    public function returned()
    {
        return Submission::where('user_id', auth()->id())
            ->whereNotNull('returned_at')
            ->pluck('legacy_batch_no')
            ->map(fn ($batchNo) => $this->responseService->generateSummaryReportByBatchNo($batchNo))
            ->filter()
            ->map(fn ($s) => Arr::only($s, ['batch_no', 'checklist_id', 'checklist_name', 'unit_id', 'unit', 'start_at', 'status', 'rejection']))
            ->values();
    }

    public function truncateResponse() {
        $this->responseService->truncateResponse();

        return response()->json([
            'message' => 'Response truncate successfully.',
        ]);
    }
}
