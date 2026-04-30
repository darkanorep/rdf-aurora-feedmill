<?php

namespace App\Http\Controllers;

use App\Http\Requests\InspectionAreaRequest;
use App\Http\Resources\InspectionAreaResource;
use App\Services\InspectionAreaService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class InspectionAreaController extends Controller
{
    use ApiResponse;

    protected $inspectionAreaService;

    public function __construct(InspectionAreaService $inspectionAreaService)
    {
        $this->inspectionAreaService = $inspectionAreaService;
    }

    public function index(Request $request) {
        $inspectionAreas = $this->inspectionAreaService->getInspectionAreas();

        if ($inspectionAreas->isEmpty()) {
            return $this->responseNotFound('No Inspection Areas found.');
        }

        return $inspectionAreas instanceof LengthAwarePaginator
            ? $inspectionAreas->through(fn($item) => new InspectionAreaResource($item))
            : $this->responseSuccess('Inspection Areas fetched successfully.', InspectionAreaResource::collection($inspectionAreas));
    }

    public function store(InspectionAreaRequest $request) {
        $data = $request->validated();
        $inspectionArea = $this->inspectionAreaService->createInspectionArea($data);

        return $this->responseCreated("Created Successfully", new InspectionAreaResource($inspectionArea));
    }

    public function show($id) {
        $inspectionArea = $this->inspectionAreaService->getInspectionAreasById($id);

        return $inspectionArea
            ? $this->responseSuccess('Inspection Area fetched successfully.', new InspectionAreaResource($inspectionArea))
            : $this->responseNotFound('Inspection Area not found.');
    }

    public function update(InspectionAreaRequest $request, $id) {
        $inspectionArea = $this->inspectionAreaService->getInspectionAreasById($id);

        if (!$inspectionArea) {
            return $this->responseNotFound('Inspection Area not found.');
        }

        $data = $request->validated();
        $updatedInspectionArea = $this->inspectionAreaService->updateInspectionArea($inspectionArea, $data);

        return $this->responseSuccess('Updated Successfully', new InspectionAreaResource($updatedInspectionArea));
    }

    public function destroy($id) {
        $inspectionArea = $this->inspectionAreaService->deleteInspectionArea($id);

        return $inspectionArea
            ? $this->responseSuccess('Status successfully changed.')
            : $this->responseNotFound('Inspection Area not found.');
    }
}
