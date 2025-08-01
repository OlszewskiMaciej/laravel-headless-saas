<?php

namespace App\Modules\Auth\Controllers;

use App\Modules\Auth\Requests\ForgotPasswordRequest;
use App\Modules\Auth\Requests\ResetPasswordRequest;
use App\Modules\Auth\Services\PasswordResetService;
use App\Core\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Routing\Controller;

class PasswordResetController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PasswordResetService $passwordResetService
    ) {
    }

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
        try {
            // Send the password reset link through service
            $status = $this->passwordResetService->sendResetLink(
                $request->only('email')
            );

            // Always return a success response to prevent email enumeration
            if ($status === Password::RESET_LINK_SENT) {
                return $this->success(null, 'If your email exists in our system, you will receive a password reset link shortly.');
            } else {
                // Log the issue but don't expose it to the client
                Log::info('Password reset status: ' . $status, ['email' => $request->email]);
                return $this->success(null, 'If your email exists in our system, you will receive a password reset link shortly.');
            }
        } catch (\Exception $e) {
            Log::error('Password reset link request failed: ' . $e->getMessage(), [
                'email' => $request->email,
                'trace' => $e->getTraceAsString()
            ]);

            // Don't expose error details to client
            return $this->success(null, 'If your email exists in our system, you will receive a password reset link shortly.');
        }
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
     *             @OA\Property(property="password", type="string", format="password", example="newpassword"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="newpassword")
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
        try {
            $status = $this->passwordResetService->resetPassword(
                $request->only('email', 'password', 'password_confirmation', 'token')
            );

            if ($status === Password::PASSWORD_RESET) {
                return $this->success(null, __($status));
            } else {
                // Map the error status to a user-friendly message
                $errorMessage = $this->mapPasswordResetError($status);
                return $this->error($errorMessage, 422);
            }
        } catch (\Exception $e) {
            Log::error('Password reset failed: ' . $e->getMessage(), [
                'email' => $request->email,
                'trace' => $e->getTraceAsString()
            ]);

            return $this->error('Unable to reset password. Please try again with a new reset link.', 500);
        }
    }

    /**
     * Map password reset error status to user-friendly messages
     */
    private function mapPasswordResetError(string $status): string
    {
        return match($status) {
            Password::INVALID_TOKEN   => 'Invalid or expired password reset token. Please request a new link.',
            Password::INVALID_USER    => 'We cannot find a user with that email address.',
            Password::RESET_THROTTLED => 'Please wait before retrying. Too many password reset attempts.',
            default                   => 'Unable to reset password. Please try again.'
        };
    }
}
