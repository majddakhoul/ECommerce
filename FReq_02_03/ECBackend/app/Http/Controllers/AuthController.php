<?php

namespace App\Http\Controllers;

use App\DTOs\ChangePasswordDTO;
use App\DTOs\LoginDTO;
use App\DTOs\RegisterDTO;
use App\DTOs\UpdateProfileDTO;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(protected AuthService $authService) {}

    public function register(RegisterRequest $request)
    {
        $result = $this->authService->register(RegisterDTO::fromRequest($request));

        return $this->created($this->sessionPayload($result), 'Registered successfully');
    }

    public function login(LoginRequest $request)
    {
        return $this->success($this->sessionPayload($this->authService->login(LoginDTO::fromRequest($request))), 'Logged in successfully');
    }

    public function me(Request $request)
    {
        return $this->success(new UserResource($this->authService->me($request->user())));
    }

    public function updateProfile(UpdateProfileRequest $request)
    {
        $user = $this->authService->updateProfile($request->user(), UpdateProfileDTO::fromRequest($request));

        return $this->success(new UserResource($user), 'Profile updated');
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $result = $this->authService->changePassword($request->user(), ChangePasswordDTO::fromRequest($request));

        return $this->success($this->sessionPayload($result), 'Password changed');
    }

    public function logout()
    {
        $this->authService->logout();

        return $this->success(null, 'Logged out successfully');
    }

    public function refresh(Request $request)
    {
        return $this->success($this->sessionPayload($this->authService->refresh($request->user())), 'Token refreshed');
    }

    private function sessionPayload(array $result): array
    {
        return [
            'user' => new UserResource($result['user']),
            ...$result['token'],
        ];
    }
}
