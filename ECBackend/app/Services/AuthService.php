<?php

namespace App\Services;

use App\DTOs\ChangePasswordDTO;
use App\DTOs\LoginDTO;
use App\DTOs\RegisterDTO;
use App\DTOs\UpdateProfileDTO;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Support\AccessControl;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\JWTGuard;

class AuthService
{
    public function __construct(protected UserRepositoryInterface $userRepo) {}

    public function register(RegisterDTO $dto): array
    {
        $user = $this->userRepo->create($dto->toArray());
        $user->assignRole(AccessControl::USER_ROLE);

        return $this->session($user, $this->guard()->login($user));
    }

    public function login(LoginDTO $dto): array
    {
        $user = $this->userRepo->findByEmail($dto->email);

        if ($user && $user->isLocked()) {
            throw $this->lockedException($user);
        }

        $token = $this->guard()->attempt([
            'email' => $dto->email,
            'password' => $dto->password,
        ]);

        if (!$token) {
            if ($user) {
                $user = $this->userRepo->recordFailedLogin(
                    $user,
                    config('api.login_max_attempts'),
                    config('api.login_lockout_minutes')
                );

                if ($user->isLocked()) {
                    throw $this->lockedException($user);
                }
            }

            throw ApiException::unauthorized('Invalid credentials');
        }

        if (!$user->is_active) {
            $this->guard()->setToken($token)->invalidate();

            throw ApiException::forbidden('Your account is disabled.');
        }

        return $this->session($this->userRepo->recordSuccessfulLogin($user), $token);
    }

    public function me(User $user): User
    {
        return $user->load('roles');
    }

    public function updateProfile(User $user, UpdateProfileDTO $dto): User
    {
        return $this->userRepo->update($user, $dto->attributes)->load('roles');
    }

    public function changePassword(User $user, ChangePasswordDTO $dto): array
    {
        if (!Hash::check($dto->currentPassword, $user->password)) {
            throw ApiException::unprocessable('Validation failed', [
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $this->userRepo->update($user, ['password' => $dto->newPassword]);

        $this->guard()->logout();

        return $this->session($user, $this->guard()->login($user));
    }

    public function logout(): void
    {
        $this->guard()->logout();
    }

    public function refresh(User $user): array
    {
        return $this->session($user, $this->guard()->refresh());
    }

    private function guard(): JWTGuard
    {
        return auth('api');
    }

    private function session(User $user, string $token): array
    {
        return [
            'user' => $user->load('roles'),
            'token' => [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => $this->guard()->factory()->getTTL() * 60,
            ],
        ];
    }

    private function lockedException(User $user): ApiException
    {
        $minutes = max(1, (int) ceil(now()->diffInSeconds($user->locked_until, false) / 60));

        return ApiException::locked(
            "Too many failed login attempts. The account is locked, try again in {$minutes} minute(s)."
        );
    }
}
