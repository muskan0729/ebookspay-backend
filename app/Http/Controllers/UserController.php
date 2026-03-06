<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\PasswordResetMail;

class UserController extends Controller
{
    // ================= REGISTER =================
    public function register(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:6|confirmed',
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role ?? "user",
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'User registered successfully',
                'access_token' => $token,
                'token_type' => 'Bearer',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ================= LOGIN =================
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'message' => 'The provided credentials are incorrect.'
                ], 401);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'User logged in successfully',
                'user_id' => $user->id,
                'name'=> $user->name,
                'email'=>$user->email,
                'role'=>$user->role,
                'access_token' => $token,
                'token_type' => 'Bearer',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ================= LOGOUT =================
    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'message' => 'User logged out successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ================= GET PROFILE =================
    public function profile(Request $request)
    {
        try {
            return response()->json($request->user());
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ================= FORGOT PASSWORD (API Token Method) =================
    public function forgotPassword(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:users,email',
            ]);

            // Generate a reset token
            $token = Str::random(60);
            
            // Store token in database (password_resets table)
            DB::table('password_resets')->updateOrInsert(
                ['email' => $request->email],
                [
                    'token' => Hash::make($token),
                    'created_at' => Carbon::now()
                ]
            );

            // Generate reset URL with token
            $resetUrl = url('/api/reset-password?token=' . $token . '&email=' . $request->email);
            
            // For production, you would send an email here
            // Mail::to($request->email)->send(new PasswordResetMail($resetUrl));
            
            // For now, return the token in response (in production, only send via email)
            return response()->json([
                'message' => 'Password reset token generated successfully',
                'reset_token' => $token, // Remove this in production - only for testing
                'reset_url' => $resetUrl, // Remove this in production - only for testing
                'note' => 'In production, this token should be sent via email'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to process forgot password request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ================= RESET PASSWORD (API Token Method) =================
    public function resetPassword(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:users,email',
                'token' => 'required|string',
                'password' => 'required|string|min:6|confirmed',
            ]);

            // Find the reset record
            $resetRecord = DB::table('password_resets')
                ->where('email', $request->email)
                ->first();

            if (!$resetRecord) {
                return response()->json([
                    'message' => 'Invalid or expired reset token'
                ], 400);
            }

            // Check if token is valid and not expired (24 hours)
            $tokenAge = Carbon::parse($resetRecord->created_at)->diffInHours(Carbon::now());
            if ($tokenAge > 24) {
                DB::table('password_resets')->where('email', $request->email)->delete();
                return response()->json([
                    'message' => 'Reset token has expired'
                ], 400);
            }

            // Verify the token
            if (!Hash::check($request->token, $resetRecord->token)) {
                return response()->json([
                    'message' => 'Invalid reset token'
                ], 400);
            }

            // Update user's password
            $user = User::where('email', $request->email)->first();
            $user->password = Hash::make($request->password);
            $user->save();

            // Delete the used reset token
            DB::table('password_resets')->where('email', $request->email)->delete();

            // Optional: Revoke all existing tokens for security
            $user->tokens()->delete();

            return response()->json([
                'message' => 'Password reset successfully. Please login with your new password.'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to reset password',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ================= UPDATE PROFILE =================
    public function updateProfile(Request $request)
    {
        try {
            $user = $request->user();

            $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $user->id,
            ]);

            if ($request->has('name')) $user->name = $request->name;
            if ($request->has('email')) $user->email = $request->email;

            $user->save();

            return response()->json([
                'message' => 'Profile updated successfully',
                'user' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ================= CHANGE PASSWORD =================
   // ================= CHANGE PASSWORD =================
public function changePassword(Request $request)
{
    try {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed|different:current_password',
        ]);

        $user = $request->user();

        // Check if current password matches
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect'
            ], 400);
        }

        // Update password
        $user->password = Hash::make($request->new_password);
        $user->save();

        // 🔐 Revoke ALL tokens (force re-login everywhere)
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password changed successfully. Please login again.'
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Failed to change password',
            'error' => $e->getMessage()
        ], 500);
    }
}

}