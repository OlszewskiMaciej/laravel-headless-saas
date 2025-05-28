<?php

namespace App\Modules\Admin\Repositories\Interfaces;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\Activitylog\Models\Activity;

interface ActivityLogRepositoryInterface
{
    public function getAllPaginated(int $perPage = 15, array $filters = [], array $sorts = [], array $with = []): LengthAwarePaginator;
}
