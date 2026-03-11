<?php

namespace App\Http\Controllers;

use App\Http\Requests\FormmRequest;
use App\Services\FormService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;

class FormController extends Controller
{
    use ApiResponse;
    protected $formService;

    public function __construct(FormService $formService)
    {
        $this->formService = $formService;
    }

    public function index() {
        $forms = $this->formService->getForms();

        return $this->responseSuccess("Forms retrieved successfully.", $forms);
    }

    public function store(FormmRequest $request) {
        $data = $request->validated();
        $this->formService->createForm($data);

        return $this->responseCreated("Form created successfully.");
    }
}
