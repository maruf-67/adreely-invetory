<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ExpenseController extends Controller
{
    /**
     * Display a listing of expenses for the business.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = Expense::where('business_id', $user->business_id)
            ->with(['expenseCategory', 'creator']);

        // Filter by category
        if ($request->has('expense_category_id')) {
            $query->where('expense_category_id', $request->expense_category_id);
        }

        // Date range filter
        if ($request->has('from_date')) {
            $query->whereDate('expense_date', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('expense_date', '<=', $request->to_date);
        }

        // Search by name or reference number
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('reference_number', 'like', "%{$search}%");
            });
        }

        $expenses = $query->latest('expense_date')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $expenses
        ]);
    }

    /**
     * Store a newly created expense.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'expense_category_id' => 'required|exists:expense_categories,id',
            'expense_date' => 'required|date',
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'reference_number' => 'nullable|string|max:255',
            'note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        // Validate expense category belongs to same business
        $expenseCategory = ExpenseCategory::where('id', $request->expense_category_id)
            ->where('business_id', $user->business_id)
            ->first();

        if (!$expenseCategory) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid expense category selected'
            ], 400);
        }

        $expense = Expense::create([
            'business_id' => $user->business_id,
            'expense_category_id' => $request->expense_category_id,
            'expense_date' => $request->expense_date,
            'name' => $request->name,
            'amount' => $request->amount,
            'reference_number' => $request->reference_number,
            'note' => $request->note,
            'created_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Expense created successfully',
            'data' => $expense->load(['expenseCategory', 'creator'])
        ], 201);
    }

    /**
     * Display the specified expense.
     */
    public function show($id): JsonResponse
    {
        $user = Auth::user();
        $expense = Expense::where('business_id', $user->business_id)
            ->with(['expenseCategory', 'creator'])
            ->find($id);

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Expense not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $expense
        ]);
    }

    /**
     * Update the specified expense.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = Auth::user();
        $expense = Expense::where('business_id', $user->business_id)->find($id);

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Expense not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'expense_category_id' => 'required|exists:expense_categories,id',
            'expense_date' => 'required|date',
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'reference_number' => 'nullable|string|max:255',
            'note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate expense category belongs to same business
        if ($request->expense_category_id !== $expense->expense_category_id) {
            $expenseCategory = ExpenseCategory::where('id', $request->expense_category_id)
                ->where('business_id', $user->business_id)
                ->first();

            if (!$expenseCategory) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid expense category selected'
                ], 400);
            }
        }

        $expense->update($request->only([
            'expense_category_id',
            'expense_date',
            'name',
            'amount',
            'reference_number',
            'note'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Expense updated successfully',
            'data' => $expense->load(['expenseCategory', 'creator'])
        ]);
    }

    /**
     * Remove the specified expense.
     */
    public function destroy($id): JsonResponse
    {
        $user = Auth::user();
        $expense = Expense::where('business_id', $user->business_id)->find($id);

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Expense not found'
            ], 404);
        }

        $expense->delete();

        return response()->json([
            'success' => true,
            'message' => 'Expense deleted successfully'
        ]);
    }

    /**
     * Get expense analytics.
     */
    public function analytics(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $query = Expense::where('business_id', $user->business_id);

        // Date range filter
        if ($request->has('from_date')) {
            $query->whereDate('expense_date', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('expense_date', '<=', $request->to_date);
        }

        $totalExpenses = $query->sum('amount');
        $expenseCount = $query->count();

        $categoryBreakdown = $query->join('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
            ->selectRaw('expense_categories.name, SUM(expenses.amount) as total, COUNT(expenses.id) as count')
            ->groupBy('expense_categories.name')
            ->get();

        $monthlyExpenses = $query->selectRaw('YEAR(expense_date) as year, MONTH(expense_date) as month, SUM(amount) as total')
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_expenses' => $totalExpenses,
                'expense_count' => $expenseCount,
                'category_breakdown' => $categoryBreakdown,
                'monthly_expenses' => $monthlyExpenses
            ]
        ]);
    }
}
