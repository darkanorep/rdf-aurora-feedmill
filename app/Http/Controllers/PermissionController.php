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

        $permissions instanceof LengthAwarePaginator
            ? $permissions->setCollection($permissions->getCollection()->transform(function ($item) {
                    return new PermissionResource($item);
                })) 
            : $permissions = PermissionResource::collection($permissions);

        return $permissions->isEmpty()
            ? $this->responseNotFound('No Permissions found.')
            : $this->responseSuccess('Permissions fetched successfully.', $permissions);
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
