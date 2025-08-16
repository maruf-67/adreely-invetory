<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserBalance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class UserBalanceController extends Controller
{
    /**
     * Get balance summary for a specific user.
     */
    public function getUserBalance($userId): JsonResponse
    {
        $authUser = Auth::user();
        $user = User::where('business_id', $authUser->business_id)->find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $balanceStatus = $user->getBalanceStatus();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'user_type' => $user->user_type,
                ],
                'balance_summary' => [
                    'previous_due' => $user->previous_due,
                    'previous_credit' => $user->previous_credit,
                    'current_balance' => $user->current_balance,
                    'total_balance' => $balanceStatus['total_balance'],
                    'status' => $balanceStatus['status'],
                    'absolute_amount' => $balanceStatus['absolute_amount'],
                ]
            ]
        ]);
    }

    /**
     * Get balance history for a specific user.
     */
    public function getUserBalanceHistory(Request $request, $userId): JsonResponse
    {
        $authUser = Auth::user();
        $user = User::where('business_id', $authUser->business_id)->find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $query = UserBalance::where('business_id', $authUser->business_id)
                           ->where('user_id', $userId)
                           ->with(['balanceable']);

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('transaction_date', [$request->start_date, $request->end_date]);
        }

        // Filter by balance type
        if ($request->has('balance_type')) {
            $query->where('balance_type', $request->balance_type);
        }

        $balanceHistory = $query->orderBy('created_at', 'desc')
                               ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $balanceHistory
        ]);
    }

    /**
     * Add manual balance adjustment.
     */
    public function addBalanceAdjustment(Request $request, $userId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
            'balance_type' => 'required|in:credit,debit',
            'description' => 'required|string|max:500',
            'reference_number' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            return DB::transaction(function () use ($request, $userId) {
                $authUser = Auth::user();
                $user = User::where('business_id', $authUser->business_id)->find($userId);

                if (!$user) {
                    return response()->json([
                        'success' => false,
                        'message' => 'User not found'
                    ], 404);
                }

                // Capture the previous balance before updating
                $previousBalance = $user->current_balance;

                // Update user balance
                $user->updateBalance($request->amount, $request->balance_type);

                // Create balance record - use the user as reference for manual adjustments
                $balanceRecord = UserBalance::create([
                    'business_id' => $authUser->business_id,
                    'user_id' => $userId,
                    'balanceable_type' => get_class($user),
                    'balanceable_id' => $user->id,
                    'amount' => $request->amount,
                    'balance_type' => $request->balance_type,
                    'previous_balance' => $previousBalance,
                    'new_balance' => $request->balance_type == 'credit' 
                        ? $previousBalance + $request->amount 
                        : $previousBalance - $request->amount,
                    'description' => $request->description,
                    'transaction_date' => now()->toDateString(),
                    'reference_number' => $request->reference_number,
                    'created_by' => $authUser->id,
                    'updated_by' => $authUser->id,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Balance adjustment added successfully',
                    'data' => [
                        'balance_record' => $balanceRecord,
                        'user_balance' => $user->fresh()->getBalanceStatus()
                    ]
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add balance adjustment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get balance summary for all users in the business grouped by user_type.
     */
    public function getBusinessBalanceSummary(Request $request): JsonResponse
    {
        $authUser = Auth::user();
        
        $query = User::where('business_id', $authUser->business_id)
                    ->where('id', '!=', $authUser->id);

        // Filter by user type
        if ($request->has('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        $users = $query->get();

        // Group users by user_type
        $usersByType = $users->groupBy('user_type');

        $groupedSummary = [];
        $overallTotals = [
            'total_credit' => 0,
            'total_due' => 0,
            'net_balance' => 0,
            'total_users' => 0
        ];

        foreach ($usersByType as $userType => $typeUsers) {
            $balanceSummary = $typeUsers->map(function ($user) {
                $balanceStatus = $user->getBalanceStatus();
                return [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'user_type' => $user->user_type,
                        'party_type' => $user->party_type,
                    ],
                    'balance' => $balanceStatus
                ];
            });

            // Calculate totals for this user type
            $creditUsers = $balanceSummary->where('balance.status', 'credit');
            $dueUsers = $balanceSummary->where('balance.status', 'due');
            
            $totalCredit = $creditUsers->sum('balance.absolute_amount');
            $totalDue = $dueUsers->sum('balance.absolute_amount');
            $netBalance = $totalDue - $totalCredit;

            $groupedSummary[$userType] = [
                'user_type' => $userType,
                'total_users' => $typeUsers->count(),
                'users_with_credit' => $creditUsers->count(),
                'users_with_due' => $dueUsers->count(),
                'users_with_zero_balance' => $balanceSummary->where('balance.status', 'balanced')->count(),
                'totals' => [
                    'total_credit' => $totalCredit,
                    'total_due' => $totalDue,
                    'net_balance' => $netBalance
                ],
                'users' => $balanceSummary->values()
            ];

            // Add to overall totals
            $overallTotals['total_credit'] += $totalCredit;
            $overallTotals['total_due'] += $totalDue;
            $overallTotals['total_users'] += $typeUsers->count();
        }

        $overallTotals['net_balance'] = $overallTotals['total_due'] - $overallTotals['total_credit'];

        return response()->json([
            'success' => true,
            'data' => [
                'overall_summary' => $overallTotals,
                'user_types' => array_keys($usersByType->toArray()),
                'grouped_by_type' => $groupedSummary,
                'total_user_types' => count($usersByType)
            ]
        ]);
    }

    /**
     * Get payment history for a user.
     */
    public function getUserPaymentHistory(Request $request, $userId): JsonResponse
    {
        $authUser = Auth::user();
        $user = User::where('business_id', $authUser->business_id)->find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $query = null;

        if ($user->user_type == 'supplier') {
            // Get payments for purchase orders where this user is the supplier
            // Get directly from the Payment model with proper relation checks
            $query = $user->supplierPayments();
            
            // Add eager loading of related models
            if ($query) {
                $query->with(['paymentMethod', 'paymentable']);
            }
        } elseif ($user->user_type == 'customer') {
            // Get payments for sales orders where this user is the customer
            $query = $user->customerPayments();
            
            // Add eager loading of related models
            if ($query) {
                $query->with(['paymentMethod', 'paymentable']);
            } else {
                // For customers, if relation not yet implemented
                return response()->json([
                    'success' => false,
                    'message' => 'Customer payment history not implemented yet'
                ], 501);
            }
        }

        if (!$query) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid user type for payment history'
            ], 400);
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('transaction_date', [$request->start_date, $request->end_date]);
        }

        $payments = $query->orderBy('transaction_date', 'desc')
                         ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $payments
        ]);
    }
}
