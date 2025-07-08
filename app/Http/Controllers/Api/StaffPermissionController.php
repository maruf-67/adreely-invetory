<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enums\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class StaffPermissionController extends Controller
{
    /**
     * Get all available permissions grouped by module.
     *
     * @return JsonResponse
     */
    public function getAvailablePermissions(): JsonResponse
    {
        $groupedPermissions = Permission::getGroupedPermissions();
        
        // Format for frontend with labels
        $formattedPermissions = [];
        foreach ($groupedPermissions as $module => $permissions) {
            $formattedPermissions[$module] = array_map(function($permission) {
                return [
                    'value' => $permission->value,
                    'label' => $permission->getLabel(),
                ];
            }, $permissions);
        }

        return response()->json([
            'success' => true,
            'data' => $formattedPermissions
        ]);
    }

    /**
     * Get current staff permissions for the business.
     *
     * @return JsonResponse
     */
    public function getCurrentPermissions(): JsonResponse
    {
        $user = Auth::user();
        $business = $user->business;

        if (!$business) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'permissions' => $business->getStaffPermissions(),
            ]
        ]);
    }

    /**
     * Update staff permissions for the business.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updatePermissions(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // Only admin can update permissions
        if ($user->user_type !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only business admin can update staff permissions'
            ], 403);
        }

        $business = $user->business;
        if (!$business) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'permissions' => 'required|array',
            'permissions.*' => 'string|in:' . implode(',', Permission::getAllPermissions()),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Remove duplicate permissions
        $permissions = array_unique($request->permissions);

        // Update business staff permissions
        $business->staff_permissions = $permissions;
        $business->save();

        return response()->json([
            'success' => true,
            'message' => 'Staff permissions updated successfully',
            'data' => [
                'permissions' => $business->getStaffPermissions(),
            ]
        ]);
    }

    /**
     * Get permissions for a specific user.
     *
     * @param int $userId
     * @return JsonResponse
     */
    public function getUserPermissions($userId): JsonResponse
    {
        $currentUser = Auth::user();
        
        $user = \App\Models\User::where('id', $userId)
            ->where('business_id', $currentUser->business_id)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'user_type' => $user->user_type,
                ],
                'permissions' => $user->getPermissions(),
            ]
        ]);
    }

    /**
     * Check if current user has specific permission.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkPermission(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'permission' => 'required|string|in:' . implode(',', Permission::getAllPermissions()),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $hasPermission = $user->hasPermission($request->permission);

        return response()->json([
            'success' => true,
            'data' => [
                'has_permission' => $hasPermission,
                'permission' => $request->permission,
            ]
        ]);
    }
}
