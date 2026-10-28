<?php

namespace App\Repositories\Contracts;

use App\DTOs\UserFilterDTO;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    public function paginate(UserFilterDTO $filter): LengthAwarePaginator;

    public function findByEmail(string $email): ?User;

    public function create(array $data): User;

    public function update(User $user, array $data): User;

    public function delete(User $user): bool;

    public function recordFailedLogin(User $user, int $maxAttempts, int $lockoutMinutes): User;

    public function recordSuccessfulLogin(User $user): User;

    public function unlock(User $user): User;
}
