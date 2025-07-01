<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UnitController extends Controller
{
    /**
     * List all units for the authenticated user's business.
     *
     * Features:
     * - Retrieves all units belonging to the user's business
     * - Returns unit data in JSON format
     *
     * Security considerations:
     * - Only authenticated users can access their business units
     *
     * @return \Illuminate\Http\JsonResponse List of units
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $units = Unit::where('business_id', $user->business_id)->get();

        return response()->json([
            'success' => true,
            'data' => $units
        ]);
    }

    /**
     * Create a new unit for the authenticated user's business.
     *
     * Features:
     * - Validates unit data
     * - Creates a new unit record
     *
     * Security considerations:
     * - Only authenticated users can create units for their business
     * - Input validation prevents malicious data injection
     *
     * @param Request $request The request containing unit data
     * @return \Illuminate\Http\JsonResponse Created unit or error message
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'short_name' => 'nullable|string|max:10',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        
        $unit = Unit::create([
            'business_id' => $user->business_id,
            'name' => $request->name,
            'short_name' => $request->short_name,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Unit created successfully',
            'data' => $unit
        ], 201);
    }

    /**
     * Retrieve a specific unit by ID for the authenticated user's business.
     *
     * Features:
     * - Loads a unit by ID if it belongs to the user's business
     *
     * Security considerations:
     * - Only authenticated users can access their business units
     *
     * @param int $id The unit ID
     * @return \Illuminate\Http\JsonResponse Unit data or error message
     */
    public function show($id): JsonResponse
    {
        $user = Auth::user();
        
        $unit = Unit::where('id', $id)
            ->where('business_id', $user->business_id)
            ->first();

        if (!$unit) {
            return response()->json([
                'success' => false,
                'message' => 'Unit not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $unit
        ]);
    }

    /**
     * Update a specific unit for the authenticated user's business.
     *
     * Features:
     * - Validates and updates unit data
     *
     * Security considerations:
     * - Only authenticated users can update their business units
     * - Input validation prevents malicious data injection
     *
     * @param Request $request The request containing unit updates
     * @param int $id The unit ID
     * @return \Illuminate\Http\JsonResponse Updated unit or error message
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = Auth::user();

        $unit = Unit::where('id', $id)
            ->where('business_id', $user->business_id)
            ->first();

        if (!$unit) {
            return response()->json([
                'success' => false,
                'message' => 'Unit not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'short_name' => 'nullable|string|max:10',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $unit->update($request->only(['name', 'short_name', 'is_active']));

        return response()->json([
            'success' => true,
            'message' => 'Unit updated successfully',
            'data' => $unit
        ]);
    }

    /**
     * Delete a specific unit from the authenticated user's business.
     *
     * Features:
     * - Deletes a unit if it has no products
     * - Returns confirmation message
     *
     * Security considerations:
     * - Only authenticated users can delete their business units
     * - Prevents deletion if unit is in use by products
     *
     * @param int $id The unit ID
     * @return \Illuminate\Http\JsonResponse Success or error message
     */
    public function destroy($id): JsonResponse
    {
        $user = Auth::user();

        $unit = Unit::where('id', $id)
            ->where('business_id', $user->business_id)
            ->first();

        if (!$unit) {
            return response()->json([
                'success' => false,
                'message' => 'Unit not found'
            ], 404);
        }

        // Check if unit has products
        if ($unit->products()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete unit that has products'
            ], 400);
        }

        $unit->delete();

        return response()->json([
            'success' => true,
            'message' => 'Unit deleted successfully'
        ]);
    }
}
