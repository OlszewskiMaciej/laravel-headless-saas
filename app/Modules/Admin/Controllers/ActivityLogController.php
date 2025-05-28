<?php

namespace App\Modules\Admin\Controllers;

use App\Modules\Admin\Resources\ActivityLogCollection;
use App\Modules\Admin\Services\ActivityLogService;
use App\Core\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Routing\Controller;

class ActivityLogController extends Controller
{
    use ApiResponse;
    
    public function __construct(
        private readonly ActivityLogService $activityLogService
    ) {}

    /**
     * List all activity logs
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $logs = $this->activityLogService->getPaginatedLogs($request);
            return $this->success(new ActivityLogCollection($logs));
        } catch (\Exception $e) {
            Log::error('Failed to retrieve activity logs: ' . $e->getMessage(), [
                'filters' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->error('Failed to retrieve activity logs', 500);
        }
    }
}
