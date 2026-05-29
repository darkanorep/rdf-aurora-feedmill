<?php

namespace App\Http\Controllers;

use App\Services\AssessService;
use Illuminate\Http\Request;

class AssessController extends Controller
{
    protected AssessService $assessService;
    public function __construct(AssessService  $assessService) {
        $this->assessService = $assessService;
    }

    public function index(Request $request) {
        return $this->assessService->getResponses($request);
    }

    public function asses(Request $request) {
        $this->assessService->assessResponses($request->all());
        return response()->json([
            'message' => 'Approved successfully.',
        ], 200);
    }
}
