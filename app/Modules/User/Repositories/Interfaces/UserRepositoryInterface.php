<?php

namespace App\Modules\User\Repositories\Interfaces;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface UserRepositoryInterface
{
    public function getAllPaginated(int $perPage = 15, array $with = [], array $filters = [], array $sorts = []): LengthAwarePaginator;
    
    public function getAll(array $with = []): Collection;
    
    public function findById(string $id, array $with = []): ?User;
    
    public function findByEmail(string $email, array $with = []): ?User;
    
    public function create(array $data): User;
    
    public function update(User $user, array $data): bool;
    
    public function delete(User $user): bool;
    
    public function syncRoles(User $user, array $roles): void;
}
