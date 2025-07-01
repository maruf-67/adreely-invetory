<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class BusinessController extends Controller
{
    /**
     * List all businesses accessible to the authenticated user.
     *
     * Features:
     * - Admins see all owned businesses
     * - Other users see their associated business
     *
     * Security considerations:
     * - Only authenticated users can access their businesses
     *
     * @return \Illuminate\Http\JsonResponse List of businesses
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        
        if ($user->user_type === 'admin') {
            $businesses = $user->ownedBusinesses()->with('owner')->get();
        } else {
            $businesses = collect([$user->business])->filter();
        }

        return response()->json([
            'success' => true,
            'data' => $businesses
        ]);
    }

    /**
     * Create a new business and assign the authenticated user as owner.
     *
     * Features:
     * - Validates business data
     * - Creates a new business and sets the user as owner
     * - Updates user's business_id
     *
     * Security considerations:
     * - Only authenticated users can create businesses
     * - Input validation prevents malicious data injection
     *
     * @param Request $request The request containing business data
     * @return \Illuminate\Http\JsonResponse Created business or error message
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'logo' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $business = Business::create([
            'name' => $request->name,
            'address' => $request->address,
            'phone' => $request->phone,
            'email' => $request->email,
            'logo' => $request->logo,
            'description' => $request->description,
            'owner_id' => Auth::id(),
            'is_active' => true,
        ]);

        // Update the user's business_id
        Auth::user()->update(['business_id' => $business->id]);

        return response()->json([
            'success' => true,
            'message' => 'Business created successfully',
            'data' => $business->load('owner')
        ], 201);
    }

    /**
     * Retrieve a specific business by ID if accessible to the user.
     *
     * Features:
     * - Loads a business by ID if user has access
     *
     * Security considerations:
     * - Only owner or associated users can access business details
     *
     * @param int $id The business ID
     * @return \Illuminate\Http\JsonResponse Business data or error message
     */
    public function show($id): JsonResponse
    {
        $user = Auth::user();
        
        $business = Business::find($id);
        
        if (!$business) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found'
            ], 404);
        }
        
        // Check if user has access to this business
        if ($user->user_type !== 'admin' && $user->business_id !== $business->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this business'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $business->load('owner')
        ]);
    }

    /**
     * Update a specific business if the authenticated user is the owner.
     *
     * Features:
     * - Validates and updates business data
     *
     * Security considerations:
     * - Only the business owner can update business details
     * - Input validation prevents malicious data injection
     *
     * @param Request $request The request containing business updates
     * @param int $id The business ID
     * @return \Illuminate\Http\JsonResponse Updated business or error message
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = Auth::user();
        
        $business = Business::find($id);
        
        if (!$business) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found'
            ], 404);
        }
        
        // Only owner can update business
        if ($business->owner_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Only business owner can update business details'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'logo' => 'nullable|string',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $business->update($request->only([
            'name', 'address', 'phone', 'email', 'logo', 'description', 'is_active'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Business updated successfully',
            'data' => $business->load('owner')
        ]);
    }

    /**
     * Delete a specific business if the authenticated user is the owner.
     *
     * Features:
     * - Deletes a business if the user is the owner
     * - Returns confirmation message
     *
     * Security considerations:
     * - Only the business owner can delete the business
     *
     * @param int $id The business ID
     * @return \Illuminate\Http\JsonResponse Success or error message
     */
    public function destroy($id): JsonResponse
    {
        $user = Auth::user();
        
        $business = Business::find($id);
        
        if (!$business) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found'
            ], 404);
        }
        
        // Only owner can delete business
        if ($business->owner_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Only business owner can delete business'
            ], 403);
        }

        $business->delete();

        return response()->json([
            'success' => true,
            'message' => 'Business deleted successfully'
        ]);
    }
}
