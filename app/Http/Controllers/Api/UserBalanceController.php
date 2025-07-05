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

                // Update user balance
                $user->updateBalance($request->amount, $request->balance_type);

                // Create balance record
                $balanceRecord = UserBalance::createRecord(
                    $authUser->business_id,
                    $userId,
                    $request->balance_type,
                    $request->amount,
                    $request->description,
                    null,
                    $request->reference_number
                );

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
     * Get balance summary for all users in the business.
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

        $balanceSummary = $users->map(function ($user) {
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

        // Separate suppliers and customers with balances
        $suppliers = $balanceSummary->filter(fn($item) => $item['user']['user_type'] === 'supplier');
        $customers = $balanceSummary->filter(fn($item) => $item['user']['user_type'] === 'customer');

        // Calculate totals
        $totalSupplierCredit = $suppliers->where('balance.status', 'credit')->sum('balance.absolute_amount');
        $totalSupplierDue = $suppliers->where('balance.status', 'due')->sum('balance.absolute_amount');
        $totalCustomerCredit = $customers->where('balance.status', 'credit')->sum('balance.absolute_amount');
        $totalCustomerDue = $customers->where('balance.status', 'due')->sum('balance.absolute_amount');

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_supplier_credit' => $totalSupplierCredit,
                    'total_supplier_due' => $totalSupplierDue,
                    'total_customer_credit' => $totalCustomerCredit,
                    'total_customer_due' => $totalCustomerDue,
                    'net_balance' => ($totalCustomerDue + $totalSupplierCredit) - ($totalCustomerCredit + $totalSupplierDue)
                ],
                'suppliers' => $suppliers->values(),
                'customers' => $customers->values()
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

        if ($user->user_type === 'supplier') {
            // Get payments for purchase orders where this user is the supplier
            $query = $user->supplierPayments()->with(['paymentMethod', 'paymentable']);
        } elseif ($user->user_type === 'customer') {
            // For customers, we'll need to implement this when sales orders are created
            return response()->json([
                'success' => false,
                'message' => 'Customer payment history not implemented yet'
            ], 501);
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
