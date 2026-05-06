<?php

namespace App\Http\Controllers;

use App\Http\Requests\MergeFormRequest;
use App\Http\Resources\MergeFormResource;
use App\Http\Resources\PermissionResource;
use App\Models\MergeForm;
use App\Services\MergeFormService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class MergeFormController extends Controller
{
    use ApiResponse;
    private $mergeFormService;
    public function __construct(MergeFormService $mergeFormService) {
        $this->mergeFormService = $mergeFormService;
    }

    public function index(Request $request) {
        $mergeForms = $this->mergeFormService->getMergeForms($request);

        if ($mergeForms->isEmpty()) {
            return $this->responseNotFound('No Merge Forms found.');
        }

        return $mergeForms instanceof LengthAwarePaginator
            ? $mergeForms->through(fn($item) => new MergeFormResource($item))
            : $this->responseSuccess('Merge Forms fetched successfully.', MergeFormResource::collection($mergeForms));
    }

    public function store(MergeFormRequest $request) {
        $data = $request->validated();
        $permission = $this->mergeFormService->mergeForm($data);

        return $this->responseCreated("Merge Successfully", $permission);
    }

    public function show($id) {
        $mergeForm = $this->mergeFormService->viewMergeFormById($id);

        return $mergeForm
            ? $this->responseSuccess('Merge Form fetched successfully.', new MergeFormResource($mergeForm))
            : $this->responseNotFound('Merge Form not found.');
    }

    public function update(MergeFormRequest $request, $id) {
        $mergeForm = $this->mergeFormService->viewMergeFormById($id);

        if (!$mergeForm) {
            return $this->responseNotFound('Merge Form not found.');
        }

        $data = $request->validated();
        $updatedMergeForm = $this->mergeFormService->updateMergeForm($mergeForm, $data);

        return $this->responseSuccess('Updated Successfully', new MergeFormResource($updatedMergeForm));
    }

    public function destroy($id) {
        $mergeForm = $this->mergeFormService->deleteMergeForm($id);

        return $mergeForm
            ? $this->responseSuccess('Status successfully changed.')
            : $this->responseNotFound('Merge Form not found.');
    }
}
