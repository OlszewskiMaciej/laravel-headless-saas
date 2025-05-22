<?php

namespace App\Modules\Swagger;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="Laravel 12 API",
 *     version="1.0.0",
 *     description="Laravel 12 API-Only Application with Authentication, Roles, Permissions and Subscriptions",
 *     @OA\Contact(
 *         email="admin@example.com"
 *     ),
 *     @OA\License(
 *         name="MIT",
 *         url="https://opensource.org/licenses/MIT"
 *     )
 * )
 * 
 * @OA\Server(
 *      url="/api",
 *      description="API Server"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 * 
 * @OA\Tag(
 *     name="Auth",
 *     description="Authentication endpoints"
 * )
 * 
 * @OA\Tag(
 *     name="User",
 *     description="User related endpoints"
 * )
 * 
 * @OA\Tag(
 *     name="Subscription",
 *     description="Subscription management endpoints"
 * )
 * 
 * @OA\Tag(
 *     name="Admin",
 *     description="Admin only endpoints"
 * )
 */
class SwaggerAnnotations
{
}
