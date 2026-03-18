<?php

namespace App\Http\Controllers;

use App\Http\Requests\InspectionAreaPestRequest;
use App\Http\Resources\InspectionAreaPestResource;
use App\Services\InspectionAreaPestService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class InspectionAreaPestController extends Controller
{
    use ApiResponse;

    protected $inspectionAreaPestService;
    public function __construct(InspectionAreaPestService $inspectionAreaPestService)
    {
        $this->inspectionAreaPestService = $inspectionAreaPestService;
    }

    public function index(Request $request) {
        $sheets = $this->inspectionAreaPestService->getSheets($request);

        $sheets instanceof LengthAwarePaginator
            ? $sheets->setCollection($sheets->getCollection()->transform(function ($item) {
            return new InspectionAreaPestResource($item);
        }))
            : $sheets = InspectionAreaPestResource::collection($sheets);

        return $sheets->isEmpty()
            ? $this->responseNotFound('No Sheets found.')
            : $this->responseSuccess('Sheets fetched successfully.', $sheets);
    }

    public function store(InspectionAreaPestRequest $request) {
        $data = $request->validated();
        $sheet = $this->inspectionAreaPestService->storeSheet($data);

        return $this->responseCreated("Sheet Created Successfully", new InspectionAreaPestResource($sheet));

    }

    public function show($id) {
        $sheet = $this->inspectionAreaPestService->showSheet($id);

        return $sheet
            ? $this->responseSuccess('Sheet fetched successfully.', new InspectionAreaPestResource($sheet))
            : $this->responseNotFound('Sheet not found.');
    }

    public function update($id, InspectionAreaPestRequest $request)
    {
        $sheet = $this->inspectionAreaPestService->showSheet($id);

        if (!$sheet) {
            return $this->responseNotFound('Sheet not found.');
        }

        $updatedSheet = $this->inspectionAreaPestService->updateSheet($sheet, $request->validated());

        return $this->responseSuccess('Updated Successfully', new InspectionAreaPestResource($updatedSheet));
    }

    public function destroy($id) {
        $sheet = $this->inspectionAreaPestService->destroySheet($id);

        return $sheet
            ? $this->responseSuccess('Sheet successfully changed.')
            : $this->responseNotFound('Sheet not found.');
    }
}
