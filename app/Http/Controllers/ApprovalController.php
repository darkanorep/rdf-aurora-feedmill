<?php

namespace App\Http\Controllers;

use App\Services\ApprovalService;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    private ApprovalService $approvalService;
    public function __construct(ApprovalService $approvalService) {
        $this->approvalService = $approvalService;
    }

    public function index(Request $request) {
        return $this->approvalService->getResponses($request);
    }

    public function approve(Request $request) {
        $this->approvalService->approveResponses($request->all());
        return response()->json([
            'message' => 'Approved successfully.',
        ], 200);
    }

    public function countStatus() {
        return  $this->approvalService->statusCount();
    }
}
