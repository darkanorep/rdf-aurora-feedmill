<?php

namespace App\Http\Controllers;

use App\Http\Requests\SectionRequest;
use App\Http\Resources\SectionResource;
use App\Services\SectionService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class SectionController extends Controller
{
    use ApiResponse;

    protected $sectionService;

    public function __construct(SectionService $sectionService) {
        $this->sectionService = $sectionService;
    }

    public function index(Request $request) {
        $sections = $this->sectionService->getSections($request);

        if ($sections->isEmpty()) {
            return $this->responseNotFound('No Sections found.');
        }

        return $sections instanceof LengthAwarePaginator
            ? $sections->through(fn($item) => new SectionResource($item))
            : $this->responseSuccess('Sections fetched successfully', SectionResource::collection($sections));
    }

    public function store(SectionRequest $request) {
        $data = $request->validated();
        $section = $this->sectionService->createSection($data);

        return $this->responseCreated("Created Successfully", new SectionResource($section));
    }

    public function show($id) {
        $section = $this->sectionService->getSectionById($id);

        return $section
            ? $this->responseSuccess('Section fetched successfully.', new SectionResource($section))
            : $this->responseNotFound('Section not found.');
    }

    public function update(SectionRequest $request, $id) {
        $section = $this->sectionService->getSectionById($id);

        if (!$section) {
            return $this->responseNotFound('Section not found.');
        }

        $data = $request->validated();
        $updatedSection = $this->sectionService->updateSection($section, $data);

        return $this->responseSuccess('Updated Successfully', new SectionResource($updatedSection));
    }

    public function destroy($id) {
        $section = $this->sectionService->deleteSection($id);

        return $section
            ? $this->responseSuccess('Section successfully deleted.')
            : $this->responseNotFound('Section not found.');
    }
}
