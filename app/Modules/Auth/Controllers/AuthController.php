<?php

namespace App\Modules\Auth\Controllers;

use App\Models\User;
use App\Modules\Auth\Requests\LoginRequest;
use App\Modules\Auth\Requests\RegisterRequest;
use App\Modules\Auth\Resources\UserResource;
use App\Modules\Core\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AuthController
{
    use ApiResponse;

    /**
     * Register a new user
     * 
     * @OA\Post(
     *     path="/auth/register",
     *     tags={"Auth"},
     *     summary="Register a new user",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password","password_confirmation"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User registered successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="User registered successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="user", type="object"),
     *                 @OA\Property(property="token", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);
        
        // Assign the free role by default
        $user->assignRole('free');
        
        // Create a token
        $token = $user->createToken('auth_token')->plainTextToken;
        
        // Log activity
        activity()->causedBy($user)->log('registered');
        
        return $this->success([
            'user' => new UserResource($user),
            'token' => $token
        ], 'User registered successfully');
    }
    
    /**
     * Login a user
     * 
     * @OA\Post(
     *     path="/auth/login",
     *     tags={"Auth"},
     *     summary="Login a user",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Login successful"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="user", type="object"),
     *                 @OA\Property(property="token", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Invalid credentials")
     * )
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();
        
        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->error('Invalid credentials', 401);
        }
        
        // Create a new token
        $token = $user->createToken('auth_token')->plainTextToken;
        
        // Log activity
        activity()->causedBy($user)->log('logged in');
        
        return $this->success([
            'user' => new UserResource($user),
            'token' => $token
        ], 'Login successful');
    }
    
    /**
     * Get authenticated user
     */
    public function me(Request $request): JsonResponse
    {
        return $this->success(new UserResource($request->user()));
    }
    
    /**
     * Logout a user
     */
    public function logout(Request $request): JsonResponse
    {
        // Log activity
        activity()->causedBy($request->user())->log('logged out');
        
        $request->user()->tokens()->delete();
        
        return $this->success(null, 'Logged out successfully');
    }
}
