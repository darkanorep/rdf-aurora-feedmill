<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class UserController extends Controller
{
    use ApiResponse;
    protected $userService;
    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index(Request $request) {
        $users = $this->userService->getUsers($request);

        if ($users->isEmpty()) {
            return $this->responseNotFound('No Users found.');
        }

        return $users instanceof LengthAwarePaginator
            ? $users->through(fn($item) => new UserResource($item))
            : $this->responseSuccess('Users fetched successfully', UserResource::collection($users));
    }

    public function store(UserRequest $request) {
        $data = $request->validated();
        $user = $this->userService->createUser($data);

        return $this->responseCreated("Created Successfully", new UserResource($user));
    }

    public function show($id) {
        $user = $this->userService->getUserById($id);

        return $user
            ? $this->responseSuccess('User fetched successfully.', new UserResource($user))
            : $this->responseNotFound('User not found.');
    }

    public function update(UserRequest $request, $id) {
        $user = $this->userService->getUserById($id);

        if (!$user) {
            return $this->responseNotFound('User not found.');
        }

        $data = $request->validated();
        $updatedUser = $this->userService->updateUser($user, $data);

        return $this->responseSuccess('Updated Successfully', new UserResource($updatedUser));
    }

    public function evaluators() {
        return $this->userService->getUsersByRole('evaluator', 'Evaluator');
    }

    public function companions() {
        return $this->userService->getUsersByRole('companion', 'Companion');
    }

    public function unitRepresentatives() {
        return $this->userService->getUsersByRole('unit representative', 'Unit Representative');
    }

    public function qualityAssurance() {
        return $this->userService->getUsersByRole('qa', 'Quality Assurance');
    }

    public function qualityAssuranceHeads() {
        return $this->userService->getUsersByRole('qa head', 'Quality Assurance Head');
    }

    public function qualityCheckers() {
        return $this->userService->getUsersByRole('qc', 'Quality Checker');
    }

    public function destroy($id) {
        $user = $this->userService->deleteUser($id);

        return $user
            ? $this->responseSuccess('User successfully deleted.')
            : $this->responseNotFound('User not found.');
    }

    public function truncateUsers() {
        $this->userService->truncateUsers();
        return $this->responseSuccess('All users have been truncated successfully.');
    }
}
