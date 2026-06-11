<?php

namespace App\Services;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function getUsers()
    {
        return User::useFilters()->dynamicPaginate();
    }

    public function createUser(array $data)
    {
        $data['password'] = Hash::make($data['username']);
        return User::create($data);
    }

    public function getUserById($id)
    {
        return User::find($id);
    }

    public function updateUser(User $user, array $data)
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);
        return $user;
    }

    public function deleteUser($id) {
        $user = User::withTrashed()->find($id);

        if (!$user) {
            return null;
        }

        if ($user->trashed()) {
            $user->restore();
        } else {
            $user->delete();
        }

        return $user;
    }

    public function getUsersByRole(string $role, string $singular)
    {
        request()->merge([
            'search' => $role,
            'pagination' => 'none',
        ]);

        $users = $this->getUsers(request());

        if ($users->isEmpty()) {
            return response()->json(['message' => "No {$singular}s found."], 404);
        }

        return response()->json([
            'success' => true,
            'message' => "{$singular}s fetched successfully",
            'data' => UserResource::collection($users)->map(fn($item) => $item->only(['id', 'full_name']))
        ]);
    }

    public function truncateUsers() {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
