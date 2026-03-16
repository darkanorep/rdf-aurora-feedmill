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

    public function index(Request $request) {
         $forms = $this->formService->getForms($request);

        return $forms->isEmpty()
            ? $this->responseNotFound('No Forms found.')
            : $this->responseSuccess("Forms retrieved successfully.", $forms);
    }

    public function store(FormmRequest $request) {
        $data = $request->validated();
        $this->formService->createForm($data);

        return $this->responseCreated("Form created successfully.");
    }

    public function getFormByChecklistId(Request $request) {
        $forms = $this->formService->getFormByChecklistId($request->checklist_id);

        if ($forms['forms']->isEmpty()) {
            return $this->responseNotFound("No forms found for the given checklist ID.");
        }

        return $this->responseSuccess("Forms retrieved successfully.", $forms);
    }

    public function updateByChecklistId(FormmRequest $request) {
        
        $data = $request->validated();
        $this->formService->updateByChecklistId($data, $request->checklist_id);

        return $this->responseSuccess("Forms updated successfully.");
    }

    public function deleteByChecklistId(Request $request) {
        
        $this->formService->deleteByChecklistId($request->checklist_id);

        return $this->responseSuccess("Form deleted successfully.");
    }
    

}
