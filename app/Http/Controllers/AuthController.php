<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Resources\UserResource;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponse;
    public function login(Request $request)
    {
        $credentials = $request->only('username', 'password');

        if (auth()->attempt($credentials)) {

            $user = auth()->user();
            $token = $user->createToken('auth_token')->plainTextToken;
            $user['token'] = $token;
            $cookie = cookie('sanctum', $token, 60 * 24);

             return $this->responseSuccess('Login successful.', [
                'user' => new UserResource($user),
                'token' => $token,
            ])->withCookie($cookie);
        }

        return $this->responseUnAuthenticated('Invalid credentials');
    }

    public function changePassword(ChangePasswordRequest $request) {
        $user = auth()->user();
        $data = $request->validated();
        $user->update(['password' => Hash::make($data['new_password'])]);

        return $this->responseSuccess('Password changed successfully.');
    }

    public function logout()
    {
        $user = auth('sanctum')->user();
        $user->tokens()->delete();

        return $this->responseSuccess('Logout successful');
    }
}
