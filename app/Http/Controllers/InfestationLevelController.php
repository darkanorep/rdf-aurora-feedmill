<?php

namespace App\Http\Controllers;

use App\Http\Requests\InfestationLevelRequest;
use App\Http\Resources\InfestationLevelResource;
use App\Services\InfestationLevelService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class InfestationLevelController extends Controller
{
    use ApiResponse;
    protected $infestationLevelService;
    public function __construct(InfestationLevelService  $infestationLevelService)
    {
        $this->infestationLevelService = $infestationLevelService;
    }

    public function index(Request $request) {
        $infestationLevels = $this->infestationLevelService->getInfestationLevels($request);

        $infestationLevels instanceof LengthAwarePaginator
            ? $infestationLevels->setCollection($infestationLevels->getCollection()->transform(function ($item) {
            return new InfestationLevelResource($item);
        }))
            : $infestationLevels = InfestationLevelResource::collection($infestationLevels);

        return $infestationLevels->isEmpty()
            ? $this->responseNotFound('No Infestation Level found.')
            : $this->responseSuccess('Infestation Level fetched successfully.', $infestationLevels);
    }

    public function store(InfestationLevelRequest $request) {
        $data = $request->validated();
        $infestationLevel = $this->infestationLevelService->createInfestationLevel($data);

        return $this->responseCreated("Created Successfully", new InfestationLevelResource($infestationLevel));
    }

    public function show($id) {
        $infestationLevel = $this->infestationLevelService->getInfestationLevelById($id);

        return $infestationLevel
            ? $this->responseSuccess('Infestation Level fetched successfully.', new InfestationLevelResource($infestationLevel))
            : $this->responseNotFound('Infestation Level not found.');
    }

    public function update(InfestationLevelRequest $request, $id) {
        $infestationLevel = $this->infestationLevelService->getInfestationLevelById($id);

        if (!$infestationLevel) {
            return $this->responseNotFound('Infestation Level not found.');
        }

        $data = $request->validated();
        $updatedInfestationLevel = $this->infestationLevelService->updateInfestationLevel($infestationLevel, $data);

        return $this->responseSuccess('Updated Successfully', new InfestationLevelResource($updatedInfestationLevel));
    }

    public function destroy($id) {
        $inspectionArea = $this->infestationLevelService->deleteInfestationLevel($id);

        return $inspectionArea
            ? $this->responseSuccess('Status successfully changed.')
            : $this->responseNotFound('Infestation Level not found.');
    }
}
