<?php

namespace App\Http\Controllers;

use App\Http\Requests\WastageRequest;
use App\Http\Resources\WastageResource;
use App\Services\WastageService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class WastageController extends Controller
{
    use ApiResponse;
    protected $wastageService;
    public function __construct(WastageService $wastageService) {
        $this->wastageService = $wastageService;
    }

    public function index(Request $request) {
        $wastages = $this->wastageService->getWastage();

        if ($wastages->isEmpty()) {
            return $this->responseNotFound('No Wastages found.');
        }

        return $wastages instanceof LengthAwarePaginator
            ? $wastages->through(fn($item) => new WastageResource($item))
            : $this->responseSuccess('Wastages fetched successfully', WastageResource::collection($wastages));
    }

    public function store(WastageRequest $request) {
        $data = $request->validated();
        $wastage = $this->wastageService->createWastage($data);

        return $this->responseCreated("Created Successfully", new WastageResource($wastage));
    }

    public function show($id) {
        $wastage = $this->wastageService->getWastageById($id);

        return $wastage
            ? $this->responseSuccess('Wastage fetched successfully.', new WastageResource($wastage))
            : $this->responseNotFound('Wastage not found.');
    }

    public function update(WastageRequest $request, $id) {
        $wastage = $this->wastageService->getWastageById($id);

        if (!$wastage) {
            return $this->responseNotFound('Wastage not found.');
        }

        $data = $request->validated();
        $updatedWastage = $this->wastageService->updateWastage($wastage, $data);

        return $this->responseSuccess('Updated Successfully', new WastageResource($updatedWastage));
    }

    public function destroy($id) {
        $wastage = $this->wastageService->deleteWastage($id);

        return $wastage
            ? $this->responseSuccess('Wastage status successfully changed.')
            : $this->responseNotFound('Wastage not found.');
    }
}
