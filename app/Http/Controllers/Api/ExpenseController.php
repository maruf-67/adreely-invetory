<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ExpenseController extends Controller
{
    /**
     * List all expenses for the authenticated user's business.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = Expense::where('business_id', $user->business_id)
            ->with(['expenseCategory', 'user']);

        // Filter by category
        if ($request->has('expense_category_id')) {
            $query->where('expense_category_id', $request->expense_category_id);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('expense_date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->where('expense_date', '<=', $request->end_date);
        }

        // Filter by month and year
        if ($request->has('month') && $request->has('year')) {
            $query->whereMonth('expense_date', $request->month)
                ->whereYear('expense_date', $request->year);
        }

        // Search by title or reference number
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%");
            });
        }

        // Sort by expense_date (default) or amount
        $sortBy = $request->get('sort_by', 'expense_date');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $expenses = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $expenses
        ]);
    }

    /**
     * Create a new expense.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'expense_category_id' => 'required|exists:expense_categories,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0',
            'expense_date' => 'required|date',
            'reference_number' => 'nullable|string|max:255',
            'receipt_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        // Validate that category belongs to the same business
        $category = ExpenseCategory::find($request->expense_category_id);
        if (!$category || $category->business_id != $user->business_id) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid expense category selected'
            ], 400);
        }

        // Handle file upload
        $receiptPath = null;
        if ($request->hasFile('receipt_file')) {
            $file = $request->file('receipt_file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $file->move(public_path('uploads/receipts'), $fileName);
            $receiptPath = 'uploads/receipts/' . $fileName;
        }

        $expense = Expense::create([
            'business_id' => $user->business_id,
            'expense_category_id' => $request->expense_category_id,
            'user_id' => $user->id,
            'title' => $request->title,
            'description' => $request->description,
            'amount' => $request->amount,
            'expense_date' => $request->expense_date,
            'reference_number' => $request->reference_number,
            'receipt_file' => $receiptPath,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Expense created successfully',
            'data' => $expense->load(['expenseCategory', 'user'])
        ], 201);
    }

    /**
     * Show a specific expense.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $user = Auth::user();

        $expense = Expense::where('id', $id)
            ->where('business_id', $user->business_id)
            ->with(['expenseCategory', 'user'])
            ->first();

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
     * Update an expense.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = Auth::user();

        $expense = Expense::where('id', $id)
            ->where('business_id', $user->business_id)
            ->first();

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Expense not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'expense_category_id' => 'sometimes|required|exists:expense_categories,id',
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'sometimes|required|numeric|min:0',
            'expense_date' => 'sometimes|required|date',
            'reference_number' => 'nullable|string|max:255',
            'receipt_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate category if provided
        if ($request->has('expense_category_id')) {
            $category = ExpenseCategory::find($request->expense_category_id);
            if (!$category || $category->business_id != $user->business_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid expense category selected'
                ], 400);
            }
        }

        $updateData = $request->only([
            'expense_category_id',
            'title',
            'description',
            'amount',
            'expense_date',
            'reference_number'
        ]);

        // Handle file upload and delete previous file
        if ($request->hasFile('receipt_file')) {
            // Delete previous file if exists
            if ($expense->receipt_file && file_exists(public_path($expense->receipt_file))) {
                unlink(public_path($expense->receipt_file));
            }

            // Upload new file
            $file = $request->file('receipt_file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $file->move(public_path('uploads/receipts'), $fileName);
            $updateData['receipt_file'] = 'uploads/receipts/' . $fileName;
        }

        $expense->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Expense updated successfully',
            'data' => $expense->load(['expenseCategory', 'user'])
        ]);
    }

    /**
     * Delete an expense.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $user = Auth::user();

        $expense = Expense::where('id', $id)
            ->where('business_id', $user->business_id)
            ->first();

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Expense not found'
            ], 404);
        }

        // Delete receipt file if exists
        if ($expense->receipt_file && file_exists(public_path($expense->receipt_file))) {
            unlink(public_path($expense->receipt_file));
        }

        $expense->delete();

        return response()->json([
            'success' => true,
            'message' => 'Expense deleted successfully'
        ]);
    }

    /**
     * Get expense summary and reports.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function reports(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = Expense::where('expenses.business_id', $user->business_id);

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('expenses.expense_date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->where('expenses.expense_date', '<=', $request->end_date);
        }

        // Monthly/Annual filters
        if ($request->has('month') && $request->has('year')) {
            $query->whereMonth('expenses.expense_date', $request->month)
                ->whereYear('expenses.expense_date', $request->year);
        } elseif ($request->has('year')) {
            $query->whereYear('expenses.expense_date', $request->year);
        }

        // Total expenses
        $totalExpenses = (clone $query)->sum('amount');
        $expenseCount = (clone $query)->count();

        // Expenses by category
        $expensesByCategory = (clone $query)
            ->join('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
            ->selectRaw('expense_categories.name as category_name, SUM(expenses.amount) as total_amount, COUNT(expenses.id) as expense_count')
            ->where('expenses.business_id', $user->business_id)
            ->groupBy('expense_categories.id', 'expense_categories.name')
            ->orderByDesc('total_amount')
            ->get();

        // Monthly breakdown (if year is specified)
        $monthlyBreakdown = [];
        if ($request->has('year')) {
            $monthlyBreakdown = (clone $query)
                ->selectRaw('MONTH(expenses.expense_date) as month, SUM(expenses.amount) as total_amount, COUNT(expenses.id) as expense_count')
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->map(function ($item) {
                    $item->month_name = Carbon::create()->month($item->month)->format('F');
                    return $item;
                });
        }

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_expenses' => $totalExpenses,
                    'expense_count' => $expenseCount,
                ],
                'expenses_by_category' => $expensesByCategory,
                'monthly_breakdown' => $monthlyBreakdown,
            ]
        ]);
    }
}
