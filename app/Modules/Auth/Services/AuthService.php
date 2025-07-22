<?php

namespace App\Modules\Auth\Services;

use App\Modules\User\Repositories\Interfaces\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {
    }

    /**
     * Register a new user
     */
    public function register(array $data): array
    {
        try {
            $userData = [
                'name'     => $data['name'],
                'email'    => $data['email'],
                'password' => Hash::make($data['password']),
            ];

            $user = $this->userRepository->create($userData);

            // Assign the free role by default
            $this->userRepository->syncRoles($user, ['free']);

            // Create a token
            $token = $user->createToken('auth_token')->plainTextToken;

            // Log activity
            activity()->causedBy($user)->log('registered');

            return [
                'user'  => $user->load('roles'),
                'token' => $token
            ];
        } catch (\Exception $e) {
            Log::error('User registration failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Authenticate user login
     */
    public function login(string $email, string $password): array
    {
        try {
            $user = $this->userRepository->findByEmail($email, ['roles']);

            if (!$user || !Hash::check($password, $user->password)) {
                throw new \InvalidArgumentException('Invalid credentials');
            }

            // Delete all previous tokens
            $user->tokens()->delete();

            // Create a new token
            $token = $user->createToken('auth_token')->plainTextToken;

            // Log activity
            activity()->causedBy($user)->log('logged in');

            return [
                'user'  => $user,
                'token' => $token
            ];
        } catch (\Exception $e) {
            Log::error('User login failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Logout user
     */
    public function logout(User $user): bool
    {
        try {
            // Revoke all tokens
            $user->tokens()->delete();

            // Log activity
            activity()->causedBy($user)->log('logged out');

            return true;
        } catch (\Exception $e) {
            Log::error('User logout failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
