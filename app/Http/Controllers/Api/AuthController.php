<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Register a new admin user with business.
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20|unique:users,phone',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'business_name' => 'required|string|max:255',
            'business_address' => 'nullable|string',
            'business_phone' => 'nullable|string|max:20',
            'business_email' => 'nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Create business first
        $business = Business::create([
            'name' => $request->business_name,
            'address' => $request->business_address,
            'phone' => $request->business_phone,
            'email' => $request->business_email,
            'is_active' => true,
        ]);

        // Create admin user
        $user = User::create([
            'business_id' => $business->id,
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'user_type' => 'admin',
            'current_balance' => 0,
        ]);

        // Update business owner_id
        $business->update(['owner_id' => $user->id]);

        // Create token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Admin registered successfully',
            'data' => [
                'user' => $user->load('business'),
                'business' => $business,
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ], 201);
    }

    /**
     * Login user.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required|string', // Can be email or phone
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $login = $request->login;
        $password = $request->password;

        // Determine if login is email or phone
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        // Find user
        $user = User::where($field, $login)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Check if user has login permissions (admin or staff)
        if (!in_array($user->user_type, ['admin', 'staff'])) {
            return response()->json([
                'success' => false,
                'message' => 'User type not allowed to login'
            ], 403);
        }

        // Check if business is active
        if ($user->business && !$user->business->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Business account is inactive'
            ], 403);
        }

        // Create token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $user->load('business'),
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ]);
    }

    /**
     * Get authenticated user profile.
     */
    public function profile(): JsonResponse
    {
        $user = Auth::user();
        
        return response()->json([
            'success' => true,
            'data' => $user->load('business', 'creator')
        ]);
    }

    /**
     * Update user profile.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:20|unique:users,phone,' . $user->id,
            'email' => 'sometimes|required|email|max:255|unique:users,email,' . $user->id,
            'image' => 'nullable|string',
            'address' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user->update($request->only([
            'name', 'phone', 'email', 'image', 'address'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => $user->load('business')
        ]);
    }

    /**
     * Change user password.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect'
            ], 400);
        }

        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully'
        ]);
    }

    /**
     * Logout user.
     */
    public function logout(): JsonResponse
    {
        Auth::user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Logout from all devices.
     */
    public function logoutAll(): JsonResponse
    {
        Auth::user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out from all devices successfully'
        ]);
    }
}
