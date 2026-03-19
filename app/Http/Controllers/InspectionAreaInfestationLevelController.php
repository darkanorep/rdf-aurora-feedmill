<?php

namespace App\Http\Controllers;

use App\Http\Requests\InspectionAreaInfestationLevelRequest;
use App\Http\Resources\InspectionAreaInfestationLevelResource;
use App\Services\InspectionAreaInfestationLevelService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class InspectionAreaInfestationLevelController extends Controller
{
    use ApiResponse;
    protected $inspectionAreaInfestationLevelService;
    public function __construct(InspectionAreaInfestationLevelService $inspectionAreaInfestationLevelService)
    {
        $this->inspectionAreaInfestationLevelService = $inspectionAreaInfestationLevelService;
    }

    public function index(Request $request) {
        $surveys = $this->inspectionAreaInfestationLevelService->getSurveys($request);

        $surveys instanceof LengthAwarePaginator
            ? $surveys->setCollection($surveys->getCollection()->transform(function ($item) {
            return new InspectionAreaInfestationLevelResource($item);
        }))
            : $surveys = InspectionAreaInfestationLevelResource::collection($surveys);

        return $surveys->isEmpty()
            ? $this->responseNotFound('No Surveys found.')
            : $this->responseSuccess('Surveys fetched successfully.', $surveys);
    }

    public function store(InspectionAreaInfestationLevelRequest $request) {
        $data = $request->validated();
        $survey = $this->inspectionAreaInfestationLevelService->storeSurvey($data);

        return $this->responseCreated("Survey Created Successfully", new InspectionAreaInfestationLevelResource($survey));
    }

    public function show($id) {
        $survey = $this->inspectionAreaInfestationLevelService->showSurvey($id);

        return $survey
            ? $this->responseSuccess('Survey fetched successfully.', new InspectionAreaInfestationLevelResource($survey))
            : $this->responseNotFound('Survey not found.');
    }

    public function update($id, InspectionAreaInfestationLevelRequest $request)
    {
        $survey = $this->inspectionAreaInfestationLevelService->showSurvey($id);

        if (!$survey) {
            return $this->responseNotFound('Survey not found.');
        }

        $updatedSurvey = $this->inspectionAreaInfestationLevelService->updateSurvey($survey, $request->validated());

        return $this->responseSuccess('Updated Successfully', new InspectionAreaInfestationLevelResource($updatedSurvey));
    }

    public function destroy($id) {
        $survey = $this->inspectionAreaInfestationLevelService->destroySurvey($id);

        return $survey
            ? $this->responseSuccess('Survey successfully changed.')
            : $this->responseNotFound('Survey not found.');
    }
}
