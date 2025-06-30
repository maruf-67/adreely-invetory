<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Investment;
use App\Models\Investor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class InvestmentController extends Controller
{
    /**
     * Display a listing of investments for the authenticated user's business.
     */
    public function index(Request $request): JsonResponse
    {
        $businessId = Auth::user()->business_id;
        
        $query = Investment::where('business_id', $businessId)
            ->with(['investor']);

        // Filter by investor if provided
        if ($request->has('investor_id')) {
            $query->where('investor_id', $request->investor_id);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('investment_date', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('investment_date', '<=', $request->end_date);
        }

        // Sort by investment date (newest first by default)
        $query->orderBy('investment_date', 'desc');

        $investments = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $investments,
            'message' => 'Investments retrieved successfully'
        ]);
    }

    /**
     * Store a newly created investment.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'investor_id' => 'required|exists:investors,id',
                'amount' => 'required|numeric|min:0',
                'investment_date' => 'required|date',
                'status' => 'sometimes|in:active,closed'
            ]);

            $businessId = Auth::user()->business_id;

            // Verify investor belongs to the same business
            $investor = Investor::where('id', $validated['investor_id'])
                ->where('business_id', $businessId)
                ->first();

            if (!$investor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Investor not found or does not belong to your business'
                ], 404);
            }

            $validated['business_id'] = $businessId;
            $validated['status'] = $validated['status'] ?? 'active';

            $investment = Investment::create($validated);
            $investment->load('investor');

            return response()->json([
                'success' => true,
                'data' => $investment,
                'message' => 'Investment created successfully'
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
     * Display the specified investment.
     */
    public function show(Investment $investment): JsonResponse
    {
        $businessId = Auth::user()->business_id;

        if ($investment->business_id !== $businessId) {
            return response()->json([
                'success' => false,
                'message' => 'Investment not found or access denied'
            ], 404);
        }

        $investment->load('investor');

        return response()->json([
            'success' => true,
            'data' => $investment,
            'message' => 'Investment retrieved successfully'
        ]);
    }

    /**
     * Update the specified investment.
     */
    public function update(Request $request, Investment $investment): JsonResponse
    {
        $businessId = Auth::user()->business_id;

        if ($investment->business_id !== $businessId) {
            return response()->json([
                'success' => false,
                'message' => 'Investment not found or access denied'
            ], 404);
        }

        try {
            $validated = $request->validate([
                'investor_id' => 'sometimes|required|exists:investors,id',
                'amount' => 'sometimes|required|numeric|min:0',
                'investment_date' => 'sometimes|required|date',
                'status' => 'sometimes|required|in:active,closed'
            ]);

            // If investor_id is being updated, verify it belongs to the same business
            if (isset($validated['investor_id'])) {
                $investor = Investor::where('id', $validated['investor_id'])
                    ->where('business_id', $businessId)
                    ->first();

                if (!$investor) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Investor not found or does not belong to your business'
                    ], 404);
                }
            }

            $investment->update($validated);
            $investment->load('investor');

            return response()->json([
                'success' => true,
                'data' => $investment,
                'message' => 'Investment updated successfully'
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
     * Remove the specified investment.
     */
    public function destroy(Investment $investment): JsonResponse
    {
        $businessId = Auth::user()->business_id;

        if ($investment->business_id !== $businessId) {
            return response()->json([
                'success' => false,
                'message' => 'Investment not found or access denied'
            ], 404);
        }

        $investment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Investment deleted successfully'
        ]);
    }

    /**
     * Get investment summary statistics.
     */
    public function summary(Request $request): JsonResponse
    {
        $businessId = Auth::user()->business_id;

        $query = Investment::where('business_id', $businessId);

        // Apply date filters if provided
        if ($request->has('start_date')) {
            $query->whereDate('investment_date', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('investment_date', '<=', $request->end_date);
        }

        $totalInvestments = $query->count();
        $totalAmount = $query->sum('amount');
        $activeInvestments = (clone $query)->where('status', 'active')->count();
        $activeAmount = (clone $query)->where('status', 'active')->sum('amount');
        $closedInvestments = (clone $query)->where('status', 'closed')->count();
        $closedAmount = (clone $query)->where('status', 'closed')->sum('amount');

        return response()->json([
            'success' => true,
            'data' => [
                'total_investments' => $totalInvestments,
                'total_amount' => $totalAmount,
                'active_investments' => $activeInvestments,
                'active_amount' => $activeAmount,
                'closed_investments' => $closedInvestments,
                'closed_amount' => $closedAmount
            ],
            'message' => 'Investment summary retrieved successfully'
        ]);
    }
}
