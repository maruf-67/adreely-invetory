<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExtraIncome;
use App\Models\ExtraIncomeType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ExtraIncomeController extends Controller
{
    /**
     * Display a listing of extra incomes for the authenticated user's business.
     */
    public function index(Request $request): JsonResponse
    {
        $businessId = Auth::user()->business_id;
        
        $query = ExtraIncome::where('business_id', $businessId)
            ->with(['incomeType']);

        // Filter by income type if provided
        if ($request->has('income_type_id')) {
            $query->where('income_type_id', $request->income_type_id);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('date', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('date', '<=', $request->end_date);
        }

        // Search by description
        if ($request->has('search')) {
            $query->where('description', 'like', '%' . $request->search . '%');
        }

        // Sort by date (newest first by default)
        $query->orderBy('date', 'desc');

        $extraIncomes = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $extraIncomes,
            'message' => 'Extra incomes retrieved successfully'
        ]);
    }

    /**
     * Store a newly created extra income.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'income_type_id' => 'required|exists:extra_income_types,id',
                'amount' => 'required|numeric|min:0',
                'date' => 'required|date',
                'description' => 'nullable|string|max:500'
            ]);

            $businessId = Auth::user()->business_id;

            // Verify income type belongs to the same business
            $incomeType = ExtraIncomeType::where('id', $validated['income_type_id'])
                ->where('business_id', $businessId)
                ->first();

            if (!$incomeType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Income type not found or does not belong to your business'
                ], 404);
            }

            $validated['business_id'] = $businessId;

            $extraIncome = ExtraIncome::create($validated);
            $extraIncome->load('incomeType');

            return response()->json([
                'success' => true,
                'data' => $extraIncome,
                'message' => 'Extra income created successfully'
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * Display the specified extra income.
     */
    public function show(ExtraIncome $extraIncome): JsonResponse
    {
        $businessId = Auth::user()->business_id;

        if ($extraIncome->business_id !== $businessId) {
            return response()->json([
                'success' => false,
                'message' => 'Extra income not found or access denied'
            ], 404);
        }

        $extraIncome->load('incomeType');

        return response()->json([
            'success' => true,
            'data' => $extraIncome,
            'message' => 'Extra income retrieved successfully'
        ]);
    }

    /**
     * Update the specified extra income.
     */
    public function update(Request $request, ExtraIncome $extraIncome): JsonResponse
    {
        $businessId = Auth::user()->business_id;

        if ($extraIncome->business_id !== $businessId) {
            return response()->json([
                'success' => false,
                'message' => 'Extra income not found or access denied'
            ], 404);
        }

        try {
            $validated = $request->validate([
                'income_type_id' => 'sometimes|required|exists:extra_income_types,id',
                'amount' => 'sometimes|required|numeric|min:0',
                'date' => 'sometimes|required|date',
                'description' => 'sometimes|nullable|string|max:500'
            ]);

            // If income type is being updated, verify it belongs to the same business
            if (isset($validated['income_type_id'])) {
                $incomeType = ExtraIncomeType::where('id', $validated['income_type_id'])
                    ->where('business_id', $businessId)
                    ->first();

                if (!$incomeType) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Income type not found or does not belong to your business'
                    ], 404);
                }
            }

            $extraIncome->update($validated);
            $extraIncome->load('incomeType');

            return response()->json([
                'success' => true,
                'data' => $extraIncome,
                'message' => 'Extra income updated successfully'
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * Remove the specified extra income.
     */
    public function destroy(ExtraIncome $extraIncome): JsonResponse
    {
        $businessId = Auth::user()->business_id;

        if ($extraIncome->business_id !== $businessId) {
            return response()->json([
                'success' => false,
                'message' => 'Extra income not found or access denied'
            ], 404);
        }

        $extraIncome->delete();

        return response()->json([
            'success' => true,
            'message' => 'Extra income deleted successfully'
        ]);
    }

    /**
     * Get extra income summary statistics.
     */
    public function summary(Request $request): JsonResponse
    {
        $businessId = Auth::user()->business_id;

        $query = ExtraIncome::where('business_id', $businessId);

        // Apply date filters if provided
        if ($request->has('start_date')) {
            $query->whereDate('date', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('date', '<=', $request->end_date);
        }

        $totalRecords = $query->count();
        $totalAmount = $query->sum('amount');

        // Group by income type
        $byIncomeType = (clone $query)
            ->join('extra_income_types', 'extra_incomes.extra_income_type_id', '=', 'extra_income_types.id')
            ->selectRaw('extra_income_types.name, COUNT(*) as count, SUM(extra_incomes.amount) as total_amount')
            ->groupBy('extra_income_types.id', 'extra_income_types.name')
            ->get();

        // Monthly trend (last 12 months)
        $monthlyTrend = (clone $query)
            ->selectRaw('DATE_FORMAT(date, "%Y-%m") as month, COUNT(*) as count, SUM(amount) as total_amount')
            ->where('date', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_records' => $totalRecords,
                'total_amount' => $totalAmount,
                'average_amount' => $totalRecords > 0 ? $totalAmount / $totalRecords : 0,
                'by_income_type' => $byIncomeType,
                'monthly_trend' => $monthlyTrend
            ],
            'message' => 'Extra income summary retrieved successfully'
        ]);
    }
}
