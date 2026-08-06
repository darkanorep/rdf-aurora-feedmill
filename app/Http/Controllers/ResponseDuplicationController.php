<?php

namespace App\Http\Controllers;

use App\Http\Requests\DuplicateResponseRequest;
use App\Models\Response;
use App\Services\ResponseDuplicationService;
use Essa\APIToolKit\Api\ApiResponse;

class ResponseDuplicationController extends Controller
{
    use ApiResponse;
    public function __construct(
        private readonly ResponseDuplicationService $duplicationService,
    ) {}

    public function store(DuplicateResponseRequest $request)
    {
        $duplicate = $this->duplicationService->duplicate($request->validated('batch_no'),
            $request->validated('duplicate_reason'));

        return $this->responseSuccess(
            message: 'Response duplicated successfully.',
            data: $duplicate->load('images'),
        );
    }

}
