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
     * Display a listing of users for the business.
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

        $query = User::where('business_id', $businessId);

        // Filter by user type if requested
        if ($request->has('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        // Filter by party type if requested
        if ($request->has('party_type')) {
            $query->where('party_type', $request->party_type);
        }

        $users = $query->with('creator')->get();

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * Store a newly created user.
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
            'image' => 'nullable|string',
            'address' => 'nullable|string',
            'user_type' => 'required|in:admin,staff,supplier,retailer,dealer,wholesaler,guest',
            'party_type' => 'nullable|in:Regular,Priority',
            'previous_due' => 'nullable|numeric',
            'previous_credit' => 'nullable|numeric',
            'password' => 'nullable|string|min:8|confirmed',
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

        $userData = [
            'business_id' => $currentUser->business_id,
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'image' => $request->image,
            'address' => $request->address,
            'user_type' => $request->user_type,
            'party_type' => $request->party_type,
            'previous_due' => $request->previous_due ?? 0,
            'previous_credit' => $request->previous_credit ?? 0,
            'current_balance' => ($request->previous_credit ?? 0) - ($request->previous_due ?? 0),
            'created_by' => $currentUser->id,
        ];

        // Only set password for admin and staff users
        if (in_array($request->user_type, ['admin', 'staff']) && $request->password) {
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
     * Display the specified user.
     */
    public function show(User $user): JsonResponse
    {
        $currentUser = Auth::user();

        // Check if user belongs to same business
        if ($user->business_id !== $currentUser->business_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this user'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $user->load('creator', 'business')
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $currentUser = Auth::user();

        // Check if user belongs to same business
        if ($user->business_id !== $currentUser->business_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this user'
            ], 403);
        }

        // Only admin can update admin users, and users can update themselves
        if ($user->user_type === 'admin' && $currentUser->user_type !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only admin can update admin users'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:20|unique:users,phone,' . $user->id,
            'email' => 'nullable|email|max:255|unique:users,email,' . $user->id,
            'image' => 'nullable|string',
            'address' => 'nullable|string',
            'party_type' => 'nullable|in:Regular,Priority',
            'previous_due' => 'nullable|numeric',
            'previous_credit' => 'nullable|numeric',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = $request->only([
            'name', 'phone', 'email', 'image', 'address', 'party_type', 'previous_due', 'previous_credit'
        ]);

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
     * Remove the specified user.
     */
    public function destroy(User $user): JsonResponse
    {
        $currentUser = Auth::user();

        // Check if user belongs to same business
        if ($user->business_id !== $currentUser->business_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this user'
            ], 403);
        }

        // Only admin can delete users, and cannot delete themselves
        if ($currentUser->user_type !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only admin can delete users'
            ], 403);
        }

        if ($user->id === $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete yourself'
            ], 400);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * Get users by type (suppliers, retailers, etc.)
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
