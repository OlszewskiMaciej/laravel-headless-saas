<?php

namespace App\Middleware;

use App\Console\Commands\ApiKey\Services\ApiKeyService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(protected ApiKeyService $apiKeyService)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $service = null, string $environment = null): Response
    {
        $apiKey = $this->apiKeyService->extractKeyFromRequest($request);
        
        if (!$apiKey) {
            return response()->json([
                'message' => 'API key is missing.',
                'status' => 'error',
            ], 401);
        }
        
        $keyModel = $this->apiKeyService->validateKey($apiKey);
        
        if (!$keyModel) {
            return response()->json([
                'message' => 'Invalid API key.',
                'status' => 'error',
            ], 401);
        }
        
        // If service is specified, validate it
        if ($service && $keyModel->service !== $service) {
            Log::warning('API key used with incorrect service', [
                'key_service' => $keyModel->service,
                'required_service' => $service,
                'request_path' => $request->path(),
            ]);
            
            return response()->json([
                'message' => 'This API key is not authorized for this service.',
                'status' => 'error',
            ], 403);
        }
        
        // If environment is specified, validate it
        if ($environment && $keyModel->environment !== $environment) {
            Log::warning('API key used with incorrect environment', [
                'key_environment' => $keyModel->environment,
                'required_environment' => $environment,
                'request_path' => $request->path(),
            ]);
            
            return response()->json([
                'message' => 'This API key is not authorized for this environment.',
                'status' => 'error',
            ], 403);
        }
        
        // Add API key to request for controllers to access if needed
        $request->attributes->set('api_key', $keyModel);
        
        return $next($request);
    }
}
