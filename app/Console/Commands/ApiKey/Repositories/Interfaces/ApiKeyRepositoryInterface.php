<?php

namespace App\Console\Commands\ApiKey\Repositories\Interfaces;

use App\Models\ApiKey;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ApiKeyRepositoryInterface
{
    public function getAllPaginated(int $perPage = 15, array $filters = [], array $sorts = []): LengthAwarePaginator;

    public function getAll(): Collection;

    public function findById(string $id): ?ApiKey;

    public function findByKey(string $hashedKey): ?ApiKey;

    public function create(array $data): ApiKey;

    public function update(ApiKey $apiKey, array $data): bool;

    public function delete(ApiKey $apiKey): bool;

    public function deactivate(ApiKey $apiKey): bool;
}
