<?php

namespace App\Http\Controllers;

use App\Http\Requests\PestRequest;
use App\Http\Resources\PestResource;
use App\Models\Pest;
use App\Services\PestService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class PestController extends Controller
{
    use ApiResponse;
    protected $pestService;

    public function __construct(PestService $pestService)
    {
        $this->pestService = $pestService;
    }

    public function index(Request $request) {
        $pests = $this->pestService->getPests($request);

        if ($pests->isEmpty()) {
            return $this->responseNotFound('No Pests found.');
        }

        return $pests instanceof LengthAwarePaginator
            ? $pests->through(fn($item) => new PestResource($item))
            : $this->responseSuccess('Pests fetched successfully.', PestResource::collection($pests));
    }

    public function store(PestRequest $request) {
        $data = $request->validated();
        $pest = $this->pestService->createPest($data);

        return $this->responseCreated("Created Successfully", new PestResource($pest));
    }

    public function show($id) {
        $pest = $this->pestService->getPestById($id);

        return $pest
            ? $this->responseSuccess('Pest fetched successfully.', new PestResource($pest))
            : $this->responseNotFound('Pest not found.');
    }

    public function update(PestRequest $request, $id) {
        $pest = $this->pestService->getPestById($id);

        if (!$pest) {
            return $this->responseNotFound('Pest not found.');
        }

        $data = $request->validated();
        $updatedRole = $this->pestService->updatePest($pest, $data);

        return $this->responseSuccess('Updated Successfully', new PestResource($updatedRole));
    }

    public function destroy($id) {
        $pest = $this->pestService->deletePest($id);

        return $pest
            ? $this->responseSuccess('Status successfully changed.')
            : $this->responseNotFound('Pest not found.');
    }

}
