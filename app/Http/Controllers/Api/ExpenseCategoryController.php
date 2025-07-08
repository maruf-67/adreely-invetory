<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ExpenseCategoryController extends Controller
{
    /**
     * List all expense categories for the authenticated user's business.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = ExpenseCategory::where('business_id', $user->business_id);

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Search by name
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $categories = $query->get();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * Create a new expense category.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
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

        $category = ExpenseCategory::create([
            'business_id' => $user->business_id,
            'name' => $request->name,
            'description' => $request->description,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Expense category created successfully',
            'data' => $category
        ], 201);
    }

    /**
     * Show a specific expense category.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $user = Auth::user();
        
        $category = ExpenseCategory::where('id', $id)
            ->where('business_id', $user->business_id)
            ->first();

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Expense category not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $category
        ]);
    }

    /**
     * Update an expense category.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = Auth::user();

        $category = ExpenseCategory::where('id', $id)
            ->where('business_id', $user->business_id)
            ->first();

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Expense category not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = $request->only(['name', 'description', 'is_active']);
        $category->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Expense category updated successfully',
            'data' => $category
        ]);
    }

    /**
     * Delete an expense category.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $user = Auth::user();

        $category = ExpenseCategory::where('id', $id)
            ->where('business_id', $user->business_id)
            ->first();

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Expense category not found'
            ], 404);
        }

        // Check if category has expenses
        if ($category->expenses()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete expense category that has expenses'
            ], 400);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Expense category deleted successfully'
        ]);
    }
}
