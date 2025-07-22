<?php

namespace App\Modules\Auth\Services;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetService
{
    /**
     * Send a reset link to the given user
     */
    public function sendResetLink(array $credentials): string
    {
        try {
            $status = Password::sendResetLink($credentials);

            // Log activity
            if ($status === Password::RESET_LINK_SENT) {
                activity()
                    ->withProperties(['email' => $credentials['email']])
                    ->log('requested password reset');
            }

            return $status;
        } catch (\Exception $e) {
            Log::error('Failed to send password reset link: ' . $e->getMessage(), [
                'email' => $credentials['email'] ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Reset the user's password
     */
    public function resetPassword(array $credentials): string
    {
        try {
            // Ensure password_confirmation exists if not provided
            if (!isset($credentials['password_confirmation']) && isset($credentials['password'])) {
                $credentials['password_confirmation'] = $credentials['password'];
            }

            $status = Password::reset(
                $credentials,
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

            return $status;
        } catch (\Exception $e) {
            Log::error('Failed to reset password: ' . $e->getMessage(), [
                'email' => $credentials['email'] ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
