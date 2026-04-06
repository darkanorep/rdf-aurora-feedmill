<?php

namespace App\Http\Controllers;

use App\Http\Requests\UnitRequest;
use App\Http\Resources\UnitResource;
use App\Services\UnitService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class UnitController extends Controller
{
    use ApiResponse;

    protected $unitService;
    public function __construct(UnitService $unitService) {
        $this->unitService = $unitService;
    }

    public function index(Request $request) {
        $units = $this->unitService->getUnits($request);

        $units instanceof LengthAwarePaginator
            ? $units->setCollection($units->getCollection()->transform(function ($item) {
            return new UnitResource($item);
        }))
            : $units = UnitResource::collection($units);

        return $units->isEmpty()
            ? $this->responseNotFound('No Units found.')
            : $this->responseSuccess('Units fetched successfully.', $units);
    }

    public function store(UnitRequest $request) {
        $data = $request->validated();
        $unit = $this->unitService->createUnit($data);

        return $this->responseCreated("Created Successfully", new UnitResource($unit));
    }

    public function show($id) {
        $unit = $this->unitService->getUnitById($id);

        return $unit
            ? $this->responseSuccess('Unit fetched successfully.', new UnitResource($unit))
            : $this->responseNotFound('Unit not found.');
    }

    public function update(UnitRequest $request, $id) {
        $unit = $this->unitService->getUnitById($id);

        if (!$unit) {
            return $this->responseNotFound('Unit not found.');
        }

        $data = $request->validated();
        $updatedUnit = $this->unitService->updateUnit($unit, $data);

        return $this->responseSuccess('Updated Successfully', new UnitResource($updatedUnit));
    }

    public function destroy($id) {
        $unit = $this->unitService->deleteUnit($id);

        return $unit
            ? $this->responseSuccess('Unit successfully deleted.')
            : $this->responseNotFound('Unit not found.');
    }
}
