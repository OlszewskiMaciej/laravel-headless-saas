<?php

namespace App\Modules\Auth\Controllers;

use App\Modules\Auth\Requests\ForgotPasswordRequest;
use App\Modules\Auth\Requests\ResetPasswordRequest;
use App\Modules\Core\Traits\ApiResponse;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController
{
    use ApiResponse;

    /**
     * Send a reset link to the given user
     * 
     * @OA\Post(
     *     path="/auth/forgot-password",
     *     tags={"Auth"},
     *     summary="Send a password reset link",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password reset link sent",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Password reset link sent to your email")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        // We will send the password reset link to this user
        $status = Password::sendResetLink(
            $request->only('email')
        );
        
        // Log activity
        if ($status === Password::RESET_LINK_SENT) {
            activity()
                ->withProperties(['email' => $request->email])
                ->log('requested password reset');
        }
        
        return $status === Password::RESET_LINK_SENT
            ? $this->success(null, __($status))
            : $this->error(__($status), 422);
    }
    
    /**
     * Reset the user's password
     * 
     * @OA\Post(
     *     path="/auth/reset-password",
     *     tags={"Auth"},
     *     summary="Reset password with token",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"token", "email", "password", "password_confirmation"},
     *             @OA\Property(property="token", type="string", example="abcdef1234567890"),
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="newpassword123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="newpassword123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password reset successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Your password has been reset")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
                
                // Log activity
                activity()->causedBy($user)->log('reset password');
            }
        );
        
        return $status === Password::PASSWORD_RESET
            ? $this->success(null, __($status))
            : $this->error(__($status), 422);
    }
}
