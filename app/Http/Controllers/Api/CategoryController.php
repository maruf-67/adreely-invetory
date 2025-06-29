<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories for the business.
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $categories = Category::where('business_id', $user->business_id)->get();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * Store a newly created category.
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
        
        $category = Category::create([
            'business_id' => $user->business_id,
            'name' => $request->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
            'data' => $category
        ], 201);
    }

    /**
     * Display the specified category.
     */
    public function show(Category $category): JsonResponse
    {
        $user = Auth::user();

        if ($category->business_id !== $user->business_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this category'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $category
        ]);
    }

    /**
     * Update the specified category.
     */
    public function update(Request $request, Category $category): JsonResponse
    {
        $user = Auth::user();

        if ($category->business_id !== $user->business_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this category'
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

        $category->update([
            'name' => $request->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'data' => $category
        ]);
    }

    /**
     * Remove the specified category.
     */
    public function destroy(Category $category): JsonResponse
    {
        $user = Auth::user();

        if ($category->business_id !== $user->business_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this category'
            ], 403);
        }

        // Check if category has products
        if ($category->products()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category that has products'
            ], 400);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully'
        ]);
    }
}
