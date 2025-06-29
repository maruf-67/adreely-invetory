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
     * Display a listing of brands for the business.
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
     * Store a newly created brand.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
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
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Brand created successfully',
            'data' => $brand
        ], 201);
    }

    /**
     * Display the specified brand.
     */
    public function show(Brand $brand): JsonResponse
    {
        $user = Auth::user();

        if ($brand->business_id !== $user->business_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this brand'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $brand
        ]);
    }

    /**
     * Update the specified brand.
     */
    public function update(Request $request, Brand $brand): JsonResponse
    {
        $user = Auth::user();

        if ($brand->business_id !== $user->business_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this brand'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $brand->update([
            'name' => $request->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Brand updated successfully',
            'data' => $brand
        ]);
    }

    /**
     * Remove the specified brand.
     */
    public function destroy(Brand $brand): JsonResponse
    {
        $user = Auth::user();

        if ($brand->business_id !== $user->business_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this brand'
            ], 403);
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
