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
     * Display a listing of the resource.
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
     * Store a newly created resource in storage.
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
     * Display the specified resource.
     */
    public function show(Business $business): JsonResponse
    {
        $user = Auth::user();
        
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
     * Update the specified resource in storage.
     */
    public function update(Request $request, Business $business): JsonResponse
    {
        $user = Auth::user();
        
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
     * Remove the specified resource from storage.
     */
    public function destroy(Business $business): JsonResponse
    {
        $user = Auth::user();
        
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
