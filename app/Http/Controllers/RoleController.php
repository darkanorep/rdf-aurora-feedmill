<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleRequest;
use App\Http\Resources\RoleResource;
use App\Services\RoleService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class RoleController extends Controller
{
    use ApiResponse;

    protected $roleService;

    public function __construct(RoleService $roleService) {
        $this->roleService = $roleService;
    }

    public function index(Request $request) {
        $roles = $this->roleService->getRoles($request);

        if ($roles->isEmpty()) {
            return $this->responseNotFound('No Roles found.');
        }

        return $roles instanceof LengthAwarePaginator
            ? $roles->through(fn($item) => new RoleResource($item))
            : $this->responseSuccess('Roles fetched successfully.', RoleResource::collection($roles));
    }

    public function store(RoleRequest $request) {
        $data = $request->validated();
        $role = $this->roleService->createRole($data);

        return $this->responseCreated("Created Successfully", new RoleResource($role));
    }

    public function show($id) {
        $role = $this->roleService->getRoleById($id);

        return $role
            ? $this->responseSuccess('Role fetched successfully.', new RoleResource($role))
            : $this->responseNotFound('Role not found.');
    }

    public function update(RoleRequest $request, $id) {
        $role = $this->roleService->getRoleById($id);

        if (!$role) {
            return $this->responseNotFound('Role not found.');
        }

        $data = $request->validated();
        $updatedRole = $this->roleService->updateRole($role, $data);

        return $this->responseSuccess('Updated Successfully', new RoleResource($updatedRole));
    }

    public function destroy($id) {
        $role = $this->roleService->deleteRole($id);

        return $role
            ? $this->responseSuccess('Status successfully changed.')
            : $this->responseNotFound('Role not found.');
    }
}
