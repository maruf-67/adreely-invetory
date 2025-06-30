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
     * Display a listing of expense categories for the business.
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $expenseCategories = ExpenseCategory::where('business_id', $user->business_id)
            ->withCount('expenses')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $expenseCategories
        ]);
    }

    /**
     * Store a newly created expense category.
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

        // Check if category name already exists for this business
        $existingCategory = ExpenseCategory::where('business_id', $user->business_id)
            ->where('name', $request->name)
            ->first();

        if ($existingCategory) {
            return response()->json([
                'success' => false,
                'message' => 'Expense category with this name already exists'
            ], 400);
        }

        $expenseCategory = ExpenseCategory::create([
            'business_id' => $user->business_id,
            'name' => $request->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Expense category created successfully',
            'data' => $expenseCategory
        ], 201);
    }

    /**
     * Display the specified expense category.
     */
    public function show($id): JsonResponse
    {
        $user = Auth::user();
        $expenseCategory = ExpenseCategory::where('business_id', $user->business_id)
            ->withCount('expenses')
            ->find($id);

        if (!$expenseCategory) {
            return response()->json([
                'success' => false,
                'message' => 'Expense category not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $expenseCategory
        ]);
    }

    /**
     * Update the specified expense category.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = Auth::user();
        $expenseCategory = ExpenseCategory::where('business_id', $user->business_id)->find($id);

        if (!$expenseCategory) {
            return response()->json([
                'success' => false,
                'message' => 'Expense category not found'
            ], 404);
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

        // Check if category name already exists for this business (excluding current)
        $existingCategory = ExpenseCategory::where('business_id', $user->business_id)
            ->where('name', $request->name)
            ->where('id', '!=', $id)
            ->first();

        if ($existingCategory) {
            return response()->json([
                'success' => false,
                'message' => 'Expense category with this name already exists'
            ], 400);
        }

        $expenseCategory->update(['name' => $request->name]);

        return response()->json([
            'success' => true,
            'message' => 'Expense category updated successfully',
            'data' => $expenseCategory
        ]);
    }

    /**
     * Remove the specified expense category.
     */
    public function destroy($id): JsonResponse
    {
        $user = Auth::user();
        $expenseCategory = ExpenseCategory::where('business_id', $user->business_id)->find($id);

        if (!$expenseCategory) {
            return response()->json([
                'success' => false,
                'message' => 'Expense category not found'
            ], 404);
        }

        // Check if category has associated expenses
        if ($expenseCategory->expenses()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete expense category that has associated expenses'
            ], 400);
        }

        $expenseCategory->delete();

        return response()->json([
            'success' => true,
            'message' => 'Expense category deleted successfully'
        ]);
    }
}
