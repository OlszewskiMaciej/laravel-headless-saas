<?php

namespace App\Modules\Admin\Repositories;

use App\Modules\Admin\Repositories\Interfaces\ActivityLogRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\Activitylog\Models\Activity;
use Spatie\QueryBuilder\QueryBuilder;

class ActivityLogRepository implements ActivityLogRepositoryInterface
{
    /**
     * Get all activity logs with pagination, filters, and sorting
     */
    public function getAllPaginated(int $perPage = 15, array $filters = [], array $sorts = [], array $with = []): LengthAwarePaginator
    {
        return QueryBuilder::for(Activity::class)
            ->allowedFilters($filters ?: ['log_name', 'description', 'subject_type', 'causer_type'])
            ->allowedSorts($sorts ?: ['created_at', 'log_name', 'description'])
            ->with($with ?: ['causer', 'subject'])
            ->latest()
            ->paginate($perPage);
    }
}
