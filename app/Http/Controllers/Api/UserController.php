<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * List all users for the authenticated user's business.
     *
     * Features:
     * - Retrieves users with optional filters (user_type, party_type)
     * - Only admin and staff can view all users
     *
     * Security considerations:
     * - Only admin and staff can access user lists
     *
     * @param Request $request The request containing filter parameters
     * @return \Illuminate\Http\JsonResponse List of users or error message
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $businessId = $user->business_id;

        if (!$businessId) {
            return response()->json([
                'success' => false,
                'message' => 'User must belong to a business'
            ], 400);
        }

        // Only admin and staff can view all users
        if (!in_array($user->user_type, ['admin', 'staff'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view users'
            ], 403);
        }

        $query = User::where('business_id', $businessId);

        // Filter by user type if requested
        if ($request->has('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        // Filter by party type if requested
        if ($request->has('party_type')) {
            $query->where('party_type', $request->party_type);
        }


        $users = $query->with('creator')->whereIn('user_type', ['supplier', 'retailer', 'dealer', 'wholesaler', 'guest'])->get();

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * Create a new user for the authenticated user's business.
     *
     * Features:
     * - Validates user data
     * - Handles image upload
     * - Only admin and staff can create users
     *
     * Security considerations:
     * - Only admin and staff can create users
     * - Input validation prevents malicious data injection
     *
     * @param Request $request The request containing user data
     * @return \Illuminate\Http\JsonResponse Created user or error message
     */
    public function store(Request $request): JsonResponse
    {
        $currentUser = Auth::user();

        // Only admin and staff can create users
        if (!in_array($currentUser->user_type, ['admin', 'staff'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to create users'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20|unique:users,phone',
            'email' => 'nullable|email|max:255|unique:users,email',
            'image' => 'nullable|file|image|max:2048',
            'address' => 'nullable|string',
            'user_type' => 'required|in:staff,supplier,retailer,dealer,wholesaler,guest',
            'party_type' => 'nullable|in:Regular,Priority',
            'previous_due' => 'nullable|numeric|min:0',
            'previous_credit' => 'nullable|numeric|min:0',
            'password' => 'nullable|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Staff can only create certain user types
        if ($currentUser->user_type === 'staff' && !in_array($request->user_type, ['supplier', 'retailer', 'dealer', 'wholesaler', 'guest'])) {
            return response()->json([
                'success' => false,
                'message' => 'Staff can only create supplier, retailer, dealer, wholesaler, or guest users'
            ], 403);
        }

        // Handle image upload
        $imagePath = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('uploads/users'), $imageName);
            $imagePath = 'uploads/users/' . $imageName;
        }

        $userData = [
            'business_id' => $currentUser->business_id,
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'image' => $imagePath,
            'address' => $request->address,
            'user_type' => $request->user_type,
            'party_type' => $request->party_type,
            'previous_due' => $request->previous_due ?? 0,
            'previous_credit' => $request->previous_credit ?? 0,
            'current_balance' => ($request->previous_credit ?? 0) - ($request->previous_due ?? 0),
            'created_by' => $currentUser->id,
        ];

        // Only set password for staff users
        if ($request->user_type === 'staff' && $request->password) {
            $userData['password'] = Hash::make($request->password);
        }

        $user = User::create($userData);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => $user->load('creator')
        ], 201);
    }

    /**
     * Retrieve a specific user by ID for the authenticated user's business.
     *
     * Features:
     * - Loads a user by ID if user has access
     * - Only admin and staff can view user details
     *
     * Security considerations:
     * - Only admin and staff can access user details
     *
     * @param int $id The user ID
     * @return \Illuminate\Http\JsonResponse User data or error message
     */
    public function show($id): JsonResponse
    {
        $currentUser = Auth::user();
        
        $user = User::find($id);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Check if user belongs to same business
        if ($user->business_id != $currentUser->business_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this user'
            ], 403);
        }

        // Only admin and staff can view user details
        if (!in_array($currentUser->user_type, ['admin', 'staff'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view user details'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $user->load('creator', 'business')
        ]);
    }

    /**
     * Update a specific user for the authenticated user's business.
     *
     * Features:
     * - Validates and updates user data
     * - Handles image upload and password update
     * - Only admin and staff can update users
     *
     * Security considerations:
     * - Only admin and staff can update users
     * - Input validation prevents malicious data injection
     *
     * @param Request $request The request containing user updates
     * @param int $id The user ID
     * @return \Illuminate\Http\JsonResponse Updated user or error message
     */
    public function update(Request $request, $id): JsonResponse
    {
        $currentUser = Auth::user();
        
        $user = User::find($id);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Check if user belongs to same business
        if ($user->business_id != $currentUser->business_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this user'
            ], 403);
        }

        // Only admin and staff can update users
        if (!in_array($currentUser->user_type, ['admin', 'staff'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update users'
            ], 403);
        }

        // Only admin can update admin and staff users
        if (in_array($user->user_type, ['admin', 'staff']) && $currentUser->user_type != 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only admin can update admin and staff users'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:20|unique:users,phone,' . $user->id,
            'email' => 'nullable|email|max:255|unique:users,email,' . $user->id,
            'image' => 'nullable|file|image|max:2048',
            'address' => 'nullable|string',
            'party_type' => 'nullable|in:Regular,Priority',
            'previous_due' => 'nullable|numeric|min:0',
            'previous_credit' => 'nullable|numeric|min:0',
            'password' => 'nullable|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = $request->only([
            'name', 'phone', 'email', 'address', 'party_type', 'previous_due', 'previous_credit'
        ]);

        // Handle image upload and delete previous image
        if ($request->hasFile('image')) {
            // Delete previous image if exists
            if ($user->image && file_exists(public_path($user->image))) {
                unlink(public_path($user->image));
            }
            
            // Upload new image
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('uploads/users'), $imageName);
            $updateData['image'] = 'uploads/users/' . $imageName;
        }

        // Update current balance if previous amounts change
        if ($request->has('previous_due') || $request->has('previous_credit')) {
            $previousCredit = $request->previous_credit ?? $user->previous_credit;
            $previousDue = $request->previous_due ?? $user->previous_due;
            $updateData['current_balance'] = $previousCredit - $previousDue;
        }

        // Update password if provided
        if ($request->password) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $user->load('creator')
        ]);
    }

    /**
     * Delete a specific user from the authenticated user's business.
     *
     * Features:
     * - Deletes a user if allowed
     * - Only admin can delete users
     * - Prevents self-deletion
     *
     * Security considerations:
     * - Only admin can delete users
     * - Prevents self-deletion
     *
     * @param int $id The user ID
     * @return \Illuminate\Http\JsonResponse Success or error message
     */
    public function destroy($id): JsonResponse
    {
        $currentUser = Auth::user();
        
        $user = User::find($id);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Check if user belongs to same business
        if ($user->business_id != $currentUser->business_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this user'
            ], 403);
        }

        // Only admin can delete users
        if ($currentUser->user_type != 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only admin can delete users'
            ], 403);
        }

        // Cannot delete yourself
        if ($user->id === $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete yourself'
            ], 400);
        }

        // Delete user image if exists
        if ($user->image && file_exists(public_path($user->image))) {
            unlink(public_path($user->image));
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * List users by type (supplier, retailer, etc.) for the authenticated user's business.
     *
     * Features:
     * - Retrieves users by type
     * - Only admin and staff can view users by type
     *
     * Security considerations:
     * - Only admin and staff can access user lists by type
     *
     * @param string $type The user type
     * @return \Illuminate\Http\JsonResponse List of users or error message
     */
    public function getByType(string $type): JsonResponse
    {
        $currentUser = Auth::user();
        $businessId = $currentUser->business_id;

        if (!$businessId) {
            return response()->json([
                'success' => false,
                'message' => 'User must belong to a business'
            ], 400);
        }

        // Only admin and staff can view users
        if (!in_array($currentUser->user_type, ['admin', 'staff'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view users'
            ], 403);
        }

        $validTypes = ['supplier', 'retailer', 'dealer', 'wholesaler', 'guest', 'staff'];
        
        if (!in_array($type, $validTypes)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid user type'
            ], 400);
        }

        $users = User::where('business_id', $businessId)
            ->where('user_type', $type)
            ->with('creator')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }
}
