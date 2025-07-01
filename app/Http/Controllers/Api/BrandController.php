<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class BrandController extends Controller
{
    /**
     * List all brands for the authenticated user's business.
     *
     * Features:
     * - Retrieves all brands belonging to the user's business
     * - Returns brand data in JSON format
     *
     * Security considerations:
     * - Only authenticated users can access their business brands
     *
     * @return \Illuminate\Http\JsonResponse List of brands
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $brands = Brand::where('business_id', $user->business_id)->get();

        return response()->json([
            'success' => true,
            'data' => $brands
        ]);
    }

    /**
     * Create a new brand for the authenticated user's business.
     *
     * Features:
     * - Validates brand data
     * - Creates a new brand record
     *
     * Security considerations:
     * - Only authenticated users can create brands for their business
     * - Input validation prevents malicious data injection
     *
     * @param Request $request The request containing brand data
     * @return \Illuminate\Http\JsonResponse Created brand or error message
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        
        $brand = Brand::create([
            'business_id' => $user->business_id,
            'name' => $request->name,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Brand created successfully',
            'data' => $brand
        ], 201);
    }

    /**
     * Retrieve a specific brand by ID for the authenticated user's business.
     *
     * Features:
     * - Loads a brand by ID if it belongs to the user's business
     *
     * Security considerations:
     * - Only authenticated users can access their business brands
     *
     * @param int $id The brand ID
     * @return \Illuminate\Http\JsonResponse Brand data or error message
     */
    public function show($id): JsonResponse
    {
        $user = Auth::user();
        
        $brand = Brand::where('id', $id)
            ->where('business_id', $user->business_id)
            ->first();

        if (!$brand) {
            return response()->json([
                'success' => false,
                'message' => 'Brand not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $brand
        ]);
    }

    /**
     * Update a specific brand for the authenticated user's business.
     *
     * Features:
     * - Validates and updates brand data
     *
     * Security considerations:
     * - Only authenticated users can update their business brands
     * - Input validation prevents malicious data injection
     *
     * @param Request $request The request containing brand updates
     * @param int $id The brand ID
     * @return \Illuminate\Http\JsonResponse Updated brand or error message
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = Auth::user();

        $brand = Brand::where('id', $id)
            ->where('business_id', $user->business_id)
            ->first();

        if (!$brand) {
            return response()->json([
                'success' => false,
                'message' => 'Brand not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $brand->update($request->only(['name', 'is_active']));

        return response()->json([
            'success' => true,
            'message' => 'Brand updated successfully',
            'data' => $brand
        ]);
    }

    /**
     * Delete a specific brand from the authenticated user's business.
     *
     * Features:
     * - Deletes a brand if it has no products
     * - Returns confirmation message
     *
     * Security considerations:
     * - Only authenticated users can delete their business brands
     * - Prevents deletion if brand is in use by products
     *
     * @param int $id The brand ID
     * @return \Illuminate\Http\JsonResponse Success or error message
     */
    public function destroy($id): JsonResponse
    {
        $user = Auth::user();

        $brand = Brand::where('id', $id)
            ->where('business_id', $user->business_id)
            ->first();

        if (!$brand) {
            return response()->json([
                'success' => false,
                'message' => 'Brand not found'
            ], 404);
        }

        // Check if brand has products
        if ($brand->products()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete brand that has products'
            ], 400);
        }

        $brand->delete();

        return response()->json([
            'success' => true,
            'message' => 'Brand deleted successfully'
        ]);
    }
}
