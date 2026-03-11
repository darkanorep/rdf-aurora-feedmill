<?php

namespace App\Services;

use App\Models\User;
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
}
