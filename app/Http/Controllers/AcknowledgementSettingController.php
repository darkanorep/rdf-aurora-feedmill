<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcknowledgementSettingRequest;
use App\Http\Resources\AcknowledgementSettingResource;
use App\Models\AcknowledgementSetting;
use App\Services\AcknowledgementSettingService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class AcknowledgementSettingController extends Controller
{
    use ApiResponse;
    private $acknowledgementSettingService;
    public function __construct(AcknowledgementSettingService  $acknowledgementSettingService) {
        $this->acknowledgementSettingService = $acknowledgementSettingService;
    }

    public function index() {
        $settings = $this->acknowledgementSettingService->getAcknowledgementSetting();

        if ($settings->isEmpty()) {
            return $this->responseNotFound('No Hierarchies found.');
        }

        return $settings instanceof LengthAwarePaginator
            ? $settings->through(fn($item) => new AcknowledgementSettingResource($item))
            : $this->responseSuccess('Hierarchies fetched successfully.', AcknowledgementSettingResource::collection($settings));
    }

    public function store(AcknowledgementSettingRequest $request) {
        $setting = $this->acknowledgementSettingService->createAcknowledgementSetting($request->all());
        return $this->responseCreated("Created Successfully.", new AcknowledgementSettingResource($setting));
    }

    public function show($id) {
        $setting = $this->acknowledgementSettingService->getAcknowledgementSettingById($id);

        return $setting
            ? $this->responseSuccess('Hierarchy fetched successfully.', new AcknowledgementSettingResource($setting))
            : $this->responseNotFound('Hierarchy not found.');
    }

    public function update(AcknowledgementSettingRequest $request, $id) {
        $hierarchy = $this->acknowledgementSettingService->getAcknowledgementSettingById($id);

        if (!$hierarchy) {
            return $this->responseNotFound('Hierarchy not found.');
        }

        $data = $request->validated();
        $updatedHierarchy = $this->acknowledgementSettingService->updateAcknowledgementSetting($hierarchy, $data);

        return $this->responseSuccess('Updated Successfully', new AcknowledgementSettingResource($updatedHierarchy));
    }

    public function destroy($id) {
        $hierarchy = $this->acknowledgementSettingService->deleteAcknowledgementSetting($id);

        return $hierarchy
            ? $this->responseSuccess('Hierarchy successfully status updated.')
            : $this->responseNotFound('Hierarchy not found.');
    }
}
