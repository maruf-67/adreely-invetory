<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Investor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class InvestorController extends Controller
{
    /**
     * List all investors for the authenticated user's business.
     *
     * Features:
     * - Retrieves all investors belonging to the user's business
     * - Supports filtering by status
     * - Returns investor data with relationships
     *
     * Security considerations:
     * - Only authenticated users can access their business investors
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse List of investors
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = Investor::where('business_id', $user->business_id)
            ->with(['creator:id,name', 'updater:id,name']);

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search by name if provided
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $investors = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $investors
        ]);
    }

    /**
     * Create a new investor for the authenticated user's business.
     *
     * Features:
     * - Validates investor data
     * - Creates a new investor record
     * - Sets business_id and created_by automatically
     *
     * Security considerations:
     * - Only authenticated users can create investors for their business
     * - Input validation prevents malicious data injection
     *
     * @param Request $request The request containing investor data
     * @return \Illuminate\Http\JsonResponse Created investor or error message
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'nid' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'investment_amount' => 'required|numeric|min:0',
            'profit_rate' => 'required|numeric|min:0|max:100',
            'investment_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        $investor = Investor::create([
            'business_id' => $user->business_id,
            'name' => $request->name,
            'phone' => $request->phone,
            'nid' => $request->nid,
            'address' => $request->address,
            'investment_amount' => $request->investment_amount,
            'profit_rate' => $request->profit_rate,
            'investment_date' => $request->investment_date,
            'notes' => $request->notes,
            'status' => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Investor created successfully',
            'data' => $investor->load(['creator:id,name'])
        ], 201);
    }

    /**
     * Retrieve a specific investor by ID for the authenticated user's business.
     *
     * Features:
     * - Loads an investor by ID if it belongs to the user's business
     * - Includes creator and updater relationships
     *
     * Security considerations:
     * - Only authenticated users can access their business investors
     *
     * @param int $id The investor ID
     * @return \Illuminate\Http\JsonResponse Investor data or error message
     */
    public function show($id): JsonResponse
    {
        $user = Auth::user();
        
        $investor = Investor::where('id', $id)
            ->where('business_id', $user->business_id)
            ->with(['creator:id,name', 'updater:id,name'])
            ->first();

        if (!$investor) {
            return response()->json([
                'success' => false,
                'message' => 'Investor not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $investor
        ]);
    }

    /**
     * Update a specific investor for the authenticated user's business.
     *
     * Features:
     * - Validates and updates investor data
     * - Sets updated_by automatically
     *
     * Security considerations:
     * - Only authenticated users can update their business investors
     * - Input validation prevents malicious data injection
     *
     * @param Request $request The request containing updated investor data
     * @param int $id The investor ID
     * @return \Illuminate\Http\JsonResponse Updated investor or error message
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = Auth::user();
        
        $investor = Investor::where('id', $id)
            ->where('business_id', $user->business_id)
            ->first();

        if (!$investor) {
            return response()->json([
                'success' => false,
                'message' => 'Investor not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'nid' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'investment_amount' => 'sometimes|required|numeric|min:0',
            'profit_rate' => 'sometimes|required|numeric|min:0|max:100',
            'investment_date' => 'sometimes|required|date',
            'close_date' => 'nullable|date',
            'status' => 'sometimes|in:active,closed,extended',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $investor->update($request->only([
            'name', 'phone', 'nid', 'address', 'investment_amount', 
            'profit_rate', 'investment_date', 'close_date', 'status', 'notes'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Investor updated successfully',
            'data' => $investor->fresh(['creator:id,name', 'updater:id,name'])
        ]);
    }

    /**
     * Delete a specific investor for the authenticated user's business.
     *
     * Features:
     * - Soft or hard deletes an investor
     *
     * Security considerations:
     * - Only authenticated users can delete their business investors
     *
     * @param int $id The investor ID
     * @return \Illuminate\Http\JsonResponse Success message or error
     */
    public function destroy($id): JsonResponse
    {
        $user = Auth::user();
        
        $investor = Investor::where('id', $id)
            ->where('business_id', $user->business_id)
            ->first();

        if (!$investor) {
            return response()->json([
                'success' => false,
                'message' => 'Investor not found'
            ], 404);
        }

        $investor->delete();

        return response()->json([
            'success' => true,
            'message' => 'Investor deleted successfully'
        ]);
    }

    /**
     * Close an investment for a specific investor.
     *
     * Features:
     * - Changes status to 'closed'
     * - Sets close_date to current date
     *
     * @param int $id The investor ID
     * @return \Illuminate\Http\JsonResponse Updated investor or error message
     */
    public function close($id): JsonResponse
    {
        $user = Auth::user();
        
        $investor = Investor::where('id', $id)
            ->where('business_id', $user->business_id)
            ->first();

        if (!$investor) {
            return response()->json([
                'success' => false,
                'message' => 'Investor not found'
            ], 404);
        }

        $investor->update([
            'status' => 'closed',
            'close_date' => now()->toDateString(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Investment closed successfully',
            'data' => $investor->fresh(['creator:id,name', 'updater:id,name'])
        ]);
    }

    /**
     * Extend an investment for a specific investor.
     *
     * Features:
     * - Changes status to 'extended'
     * - Resets close_date to null
     * - Updates investment_date to current date
     *
     * @param int $id The investor ID
     * @return \Illuminate\Http\JsonResponse Updated investor or error message
     */
    public function extend($id): JsonResponse
    {
        $user = Auth::user();
        
        $investor = Investor::where('id', $id)
            ->where('business_id', $user->business_id)
            ->first();

        if (!$investor) {
            return response()->json([
                'success' => false,
                'message' => 'Investor not found'
            ], 404);
        }

        $investor->update([
            'status' => 'extended',
            'close_date' => null,
            'investment_date' => now()->toDateString(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Investment extended successfully',
            'data' => $investor->fresh(['creator:id,name', 'updater:id,name'])
        ]);
    }

    /**
     * Calculate profit for a specific investor.
     *
     * Features:
     * - Calculates profit based on investment amount and profit rate
     * - Returns calculated profit amount
     *
     * @param int $id The investor ID
     * @return \Illuminate\Http\JsonResponse Profit calculation or error message
     */
    public function calculateProfit($id): JsonResponse
    {
        $user = Auth::user();
        
        $investor = Investor::where('id', $id)
            ->where('business_id', $user->business_id)
            ->first();

        if (!$investor) {
            return response()->json([
                'success' => false,
                'message' => 'Investor not found'
            ], 404);
        }

        $profit = $investor->calculateProfit();

        return response()->json([
            'success' => true,
            'data' => [
                'investor_id' => $investor->id,
                'investor_name' => $investor->name,
                'investment_amount' => $investor->investment_amount,
                'profit_rate' => $investor->profit_rate,
                'calculated_profit' => $profit,
                'total_return' => $investor->investment_amount + $profit
            ]
        ]);
    }

    /**
     * Get investment summary for the business.
     *
     * Features:
     * - Returns total investments, active investments, closed investments
     * - Calculates total profit potential
     *
     * @return \Illuminate\Http\JsonResponse Investment summary
     */
    public function summary(): JsonResponse
    {
        $user = Auth::user();
        
        $investors = Investor::where('business_id', $user->business_id)->get();
        
        $summary = [
            'total_investors' => $investors->count(),
            'active_investors' => $investors->where('status', 'active')->count(),
            'closed_investors' => $investors->where('status', 'closed')->count(),
            'extended_investors' => $investors->where('status', 'extended')->count(),
            'total_investment_amount' => $investors->sum('investment_amount'),
            'total_profit_return' => $investors->sum('profit_return'),
            'potential_profit' => $investors->sum(function($investor) {
                return $investor->calculateProfit();
            })
        ];

        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }
}
