<?php

namespace App\Repositories\Eloquent;

use App\DTOs\UserFilterDTO;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class UserRepository implements UserRepositoryInterface
{
    public function paginate(UserFilterDTO $filter): LengthAwarePaginator
    {
        return User::with('roles')
            ->when($filter->search, function ($query, $term) {
                $term = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);

                $query->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->when($filter->role, fn ($query, $role) => $query->whereHas(
                'roles',
                fn ($inner) => $inner->where('name', $role)
            ))
            ->when($filter->isActive !== null, fn ($query) => $query->where('is_active', $filter->isActive))
            ->orderBy('id')
            ->paginate($filter->perPage)
            ->withQueryString();
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user;
    }

    public function delete(User $user): bool
    {
        return (bool) $user->delete();
    }

    public function recordFailedLogin(User $user, int $maxAttempts, int $lockoutMinutes): User
    {
        $attempts = $user->failed_login_attempts + 1;

        $user->failed_login_attempts = $attempts;

        if ($attempts >= $maxAttempts) {
            $user->locked_until = now()->addMinutes($lockoutMinutes);
            $user->failed_login_attempts = 0;
        }

        $user->save();

        return $user;
    }

    public function recordSuccessfulLogin(User $user): User
    {
        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
        ])->save();

        return $user;
    }

    public function unlock(User $user): User
    {
        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->save();

        return $user;
    }
}
