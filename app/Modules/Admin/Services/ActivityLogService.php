<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Repositories\Interfaces\ActivityLogRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class ActivityLogService
{
    public function __construct(
        private readonly ActivityLogRepositoryInterface $activityLogRepository
    ) {}

    /**
     * Get paginated activity logs with filters
     */
    public function getPaginatedLogs(Request $request): LengthAwarePaginator
    {
        $perPage = $request->input('per_page', 15);
        
        return $this->activityLogRepository->getAllPaginated(
            $perPage,
            ['log_name', 'description', 'subject_type', 'causer_type'],
            ['created_at', 'log_name', 'description'],
            ['causer', 'subject']
        );
    }
}
