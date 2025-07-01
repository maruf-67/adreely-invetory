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
     * Display a listing of units for the business.
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
     * Store a newly created unit.
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
     * Display the specified unit.
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
     * Update the specified unit.
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
     * Remove the specified unit.
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
