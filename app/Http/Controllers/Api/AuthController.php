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
     * Register a new admin user (business owner)
     *
     * Registers a new admin user and optionally creates a business.
     *
     * Features:
     * - Validates user and business data
     * - Creates a business if business_name is provided
     * - Creates an admin user and links to business
     * - Issues an access token for API usage
     *
     * Security considerations:
     * - Ensures unique email for user
     * - Input validation prevents malicious data injection
     *
     * @param Request $request The request containing registration data
     * @return \Illuminate\Http\JsonResponse Registered user info and token or error message
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'business_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Create business first if business_name is provided
        $business = Business::create([
            'name' => $request->business_name ?? null,
            'is_active' => true,
        ]);

        // Create admin user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'user_type' => 'admin',
            'business_id' => $business ? $business->id : null,
        ]);

        // Update business owner_id if business was created
        if ($business) {
            $business->update(['owner_id' => $user->id]);
        }

        // Create access token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Admin registered successfully',
            'data' => [
                'user' => $user->load('business'),
                'access_token' => $token,
                'token_type' => 'Bearer'
            ]
        ], 201);
    }

    /**
     * Log in an admin or staff user.
     *
     * Authenticates an admin or staff user and issues an access token for API usage.
     *
     * Features:
     * - Validates credentials
     * - Checks user type (admin/staff)
     * - Issues access token
     * - Returns user info and token
     *
     * Security considerations:
     * - Only admin and staff users can log in
     * - Input validation prevents malicious data injection
     * - Returns error for invalid credentials or unauthorized access
     *
     * @param Request $request The request containing login credentials
     * @return \Illuminate\Http\JsonResponse Authenticated user info or error message
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Find user by email
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Check if user is admin or staff
        if (!in_array($user->user_type, ['admin', 'staff'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only admin and staff users can login'
            ], 403);
        }

        // Check password
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Create access token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $user->load('business'),
                'access_token' => $token,
                'token_type' => 'Bearer'
            ]
        ]);
    }

    /**
     * Log out the authenticated user.
     *
     * Revokes the current access token for the authenticated user.
     *
     * Features:
     * - Deletes the current access token
     * - Returns confirmation message
     *
     * Security considerations:
     * - Only authenticated users can log out
     *
     * @param Request $request The request instance
     * @return \Illuminate\Http\JsonResponse Success confirmation
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Retrieve the authenticated user's profile.
     *
     * Loads the user's profile, business, and creator information.
     *
     * Features:
     * - Returns user, business, and creator info
     *
     * Security considerations:
     * - Only authenticated users can access their profile
     *
     * @return \Illuminate\Http\JsonResponse User profile data
     */
    public function profile(): JsonResponse
    {
        $user = User::with(['business', 'creator'])->find(Auth::id());

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    /**
     * Update the authenticated user's profile.
     *
     * Allows the user to update their profile information and image.
     *
     * Features:
     * - Validates and updates user profile fields
     * - Handles image upload and replacement
     *
     * Security considerations:
     * - Only authenticated users can update their profile
     * - Input validation prevents malicious data injection
     *
     * @param Request $request The request containing profile updates
     * @return \Illuminate\Http\JsonResponse Updated user profile or error message
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'image' => 'nullable|file|image|max:2048',
            'party_type' => 'nullable|in:Regular,Priority',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($user->image && file_exists(public_path($user->image))) {
                unlink(public_path($user->image));
            }

            $image = $request->file('image');
            $imageName = 'user_' . $user->id . '_' . time() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('uploads/users'), $imageName);
            $user->image = 'uploads/users/' . $imageName;
        }

        $user->update($request->only([
            'name',
            'email',
            'phone',
            'address',
            'party_type'
        ]));

        // Save the image path if it was uploaded
        if ($request->hasFile('image')) {
            $user->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => $user->load('business')
        ]);
    }

    /**
     * Change the authenticated user's password.
     *
     * Allows the user to change their password after validating the current password.
     *
     * Features:
     * - Validates current and new passwords
     * - Updates password securely
     *
     * Security considerations:
     * - Only authenticated users can change their password
     * - Input validation and password hashing
     *
     * @param Request $request The request containing password data
     * @return \Illuminate\Http\JsonResponse Success or error message
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

        // Check current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect'
            ], 400);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully'
        ]);
    }

    /**
     * Log out from all devices.
     *
     * Revokes all access tokens for the authenticated user, logging them out from all devices.
     *
     * Features:
     * - Deletes all tokens for the user
     * - Returns confirmation message
     *
     * Security considerations:
     * - Only authenticated users can log out from all devices
     *
     * @param Request $request The request instance
     * @return \Illuminate\Http\JsonResponse Success confirmation
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out from all devices successfully'
        ]);
    }
}
