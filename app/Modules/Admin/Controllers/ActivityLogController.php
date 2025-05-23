<?php

namespace App\Modules\Admin\Controllers;

use App\Modules\Admin\Resources\ActivityLogCollection;
use App\Modules\Core\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Spatie\QueryBuilder\QueryBuilder;

class ActivityLogController
{
    use ApiResponse;

    /**
     * List all activity logs
     */
    public function index(Request $request): JsonResponse
    {
        $logs = QueryBuilder::for(Activity::class)
            ->allowedFilters(['log_name', 'description', 'subject_type', 'causer_type'])
            ->allowedSorts(['created_at', 'log_name', 'description'])
            ->with(['causer', 'subject'])
            ->latest()
            ->paginate($request->per_page ?? 15)
            ->appends($request->query());
        
        return $this->success(new ActivityLogCollection($logs));
    }
}
