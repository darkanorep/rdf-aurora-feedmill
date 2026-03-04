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

        $users instanceof LengthAwarePaginator
            ? $users->setCollection($users->getCollection()->transform(function ($item) {
                    return new UserResource($item);
                })) 
            : $users = UserResource::collection($users);

        return $users->isEmpty()
            ? $this->responseNotFound('No Users found.')
            : $this->responseSuccess('Users fetched successfully.', $users);
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

    public function destroy($id) {
        $user = $this->userService->deleteUser($id);
    
        return $user
            ? $this->responseSuccess('User successfully deleted.')
            : $this->responseNotFound('User not found.');
    }
}
