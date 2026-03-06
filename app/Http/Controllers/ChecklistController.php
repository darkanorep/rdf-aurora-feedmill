<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChecklistRequest;
use App\Http\Requests\StoreChecklistRequest;
use App\Http\Requests\UpdateChecklistRequest;
use App\Http\Resources\ChecklistResource;
use App\Models\Checklist;
use App\Services\ChecklistService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class ChecklistController extends Controller
{
    use ApiResponse;

    protected $checklistService;

    public function __construct(ChecklistService $checklistService) {
        $this->checklistService = $checklistService;
    }

    public function index() {
        $checklists = $this->checklistService->getChecklists();

        $checklists instanceof LengthAwarePaginator
            ? $checklists->setCollection($checklists->getCollection()->transform(function ($item) {
                    return new ChecklistResource($item);
                })) 
            : $checklists = ChecklistResource::collection($checklists);

        return $checklists->isEmpty()
            ? $this->responseNotFound('No Checklists found.')
            : $this->responseSuccess('Checklists fetched successfully.', $checklists);
    }

    public function store(ChecklistRequest $request) {
        $data = $request->validated();
        $checklist = $this->checklistService->createChecklist($data);

        return $this->responseCreated("Created Successfully", new ChecklistResource($checklist));
    }

    public function show($id) {
        $checklist = $this->checklistService->getChecklistById($id);

        return $checklist 
            ? $this->responseSuccess('Checklist fetched successfully.', new ChecklistResource($checklist)) 
            : $this->responseNotFound('Checklist not found.');
    }
    
    public function update(ChecklistRequest $request, $id) {
        $checklist = $this->checklistService->getChecklistById($id);

        if (!$checklist) {
            return $this->responseNotFound('Checklist not found.');
        }

        $data = $request->validated();
        $updatedChecklist = $this->checklistService->updateChecklist($checklist, $data);

        return $this->responseSuccess('Updated Successfully', new ChecklistResource($updatedChecklist));
    }

    public function destroy($id) {
        $checklist = $this->checklistService->deleteChecklist($id);
    
        return $checklist
            ? $this->responseSuccess('Checklist successfully deleted.')
            : $this->responseNotFound('Checklist not found.');
    }
}
