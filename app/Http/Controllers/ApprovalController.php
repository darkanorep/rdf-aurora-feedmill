<?php

namespace App\Http\Controllers;

use App\Services\ApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function __construct(private readonly ApprovalService $approvalService) {}

    public function index(Request $request)
    {
        return $this->approvalService->getResponses($request);
    }

    public function approve(Request $request): JsonResponse
    {
        $request->validate([
            'batch_no'           => ['required', 'integer'],
            'evaluate_image.*.*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'approve_image.*.*'  => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'assess_image.*.*'   => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'action'  => ['nullable', 'in:approve,reject'],
            'comment' => ['nullable', 'string', 'max:1000', 'required_if:action,reject'],
        ]);
        $rejected = $request->input('action') === 'reject';

        $this->approvalService->approveResponses($request->all());

        return response()->json(['message' => $rejected ? 'Returned to the auditor.' : 'Approved successfully.'], 200);
    }

    public function countStatus()
    {
        return $this->approvalService->statusCount();
    }
}
