<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\PermissionRequest;
use App\Services\PermissionService;
use Essa\APIToolKit\Api\ApiResponse;
use App\Http\Resources\PermissionResource;
use Illuminate\Pagination\LengthAwarePaginator;

class PermissionController extends Controller
{

    use ApiResponse;

    protected $permissionService;

    public function __construct(PermissionService $permissionService) {
        $this->permissionService = $permissionService;
    }

    public function index(Request $request) {
        $permissions = $this->permissionService->getPermissions($request);

        if ($permissions->isEmpty()) {
            return $this->responseNotFound('No Permissions found.');
        }

        return $permissions instanceof LengthAwarePaginator
            ? $permissions->through(fn($item) => new PermissionResource($item))
            : $this->responseSuccess('Permissions fetched successfully.', PermissionResource::collection($permissions));
    }

    public function store(PermissionRequest $request) {
        $data = $request->validated();
        $permission = $this->permissionService->createPermission($data);

        return $this->responseCreated("Created Successfully", new PermissionResource($permission));
    }

    public function show($id) {
        $permission = $this->permissionService->getPermissionById($id);

        return $permission
            ? $this->responseSuccess('Permission fetched successfully.', new PermissionResource($permission))
            : $this->responseNotFound('Permission not found.');
    }

    public function update(PermissionRequest $request, $id) {
        $permission = $this->permissionService->getPermissionById($id);

        if (!$permission) {
            return $this->responseNotFound('Permission not found.');
        }

        $data = $request->validated();
        $updatedPermission = $this->permissionService->updatePermission($permission, $data);

        return $this->responseSuccess('Updated Successfully', new PermissionResource($updatedPermission));
    }

    public function destroy($id) {
        $permission = $this->permissionService->deletePermission($id);

        return $permission
            ? $this->responseSuccess('Status successfully changed.')
            : $this->responseNotFound('Permission not found.');
    }
}
