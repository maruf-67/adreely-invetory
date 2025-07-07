<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\UserBalance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    /**
     * Get all payments for the business.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = Payment::where('business_id', $user->business_id)
                       ->with(['paymentMethod', 'paymentable']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by payment method
        if ($request->has('payment_method_id')) {
            $query->where('payment_method_id', $request->payment_method_id);
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('transaction_date', [$request->start_date, $request->end_date]);
        }

        // Filter by paymentable type
        if ($request->has('type')) {
            $types = [
                'purchase' => 'App\\Models\\PurchaseOrder',
                'sales' => 'App\\Models\\SalesOrder',
            ];
            if (isset($types[$request->type])) {
                $query->where('paymentable_type', $types[$request->type]);
            }
        }

        $payments = $query->orderBy('transaction_date', 'desc')
                         ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $payments
        ]);
    }

    /**
     * Get payments related to a specific purchase order.
     */
    public function getPurchaseOrderPayments(Request $request, $id = null)
    {
        $user = Auth::user();

        $query = Payment::where('business_id', $user->business_id);

        if ($id) {
            $query->where('paymentable_id', $id)
                  ->where('paymentable_type', 'App\\Models\\PurchaseOrder');
        }

        $payments = $query->with(['paymentMethod', 'paymentable'])
                           ->orderBy('transaction_date', 'desc')
                           ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $payments
        ]);
    }


    /**
     * Get payment summary by status.
     */
    public function getSummary(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $query = Payment::where('business_id', $user->business_id);

        // Filter by date range if provided
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('transaction_date', [$request->start_date, $request->end_date]);
        }

        $summary = $query->selectRaw('
            status,
            COUNT(*) as count,
            SUM(amount) as total_amount
        ')
        ->groupBy('status')
        ->get()
        ->keyBy('status');

        // Get payment method breakdown
        $methodBreakdown = Payment::where('business_id', $user->business_id)
            ->join('payment_methods', 'payments.payment_method_id', '=', 'payment_methods.id')
            ->selectRaw('
                payment_methods.name as method_name,
                payment_methods.type as method_type,
                payments.status,
                COUNT(*) as count,
                SUM(payments.amount) as total_amount
            ')
            ->groupBy('payment_methods.id', 'payment_methods.name', 'payment_methods.type', 'payments.status')
            ->get()
            ->groupBy('method_name');

        return response()->json([
            'success' => true,
            'data' => [
                'status_summary' => $summary,
                'method_breakdown' => $methodBreakdown,
                'totals' => [
                    'cleared' => $summary[Payment::STATUS_CLEAR]->total_amount ?? 0,
                    'pending' => $summary[Payment::STATUS_PENDING]->total_amount ?? 0,
                    'bounced' => $summary[Payment::STATUS_BOUNCED]->total_amount ?? 0,
                    'cancelled' => $summary[Payment::STATUS_CANCELLED]->total_amount ?? 0,
                ]
            ]
        ]);
    }

    /**
     * Update payment status.
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,clear,bounced,cancelled',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            return DB::transaction(function () use ($request, $id) {
                $user = Auth::user();
                $payment = Payment::where('business_id', $user->business_id)->find($id);

                if (!$payment) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Payment not found'
                    ], 404);
                }

                $oldStatus = $payment->status;
                $newStatus = $request->status;

                if ($oldStatus == $newStatus) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Payment is already in the requested status'
                    ], 400);
                }

                // Update payment status
                $payment->updateStatus($newStatus, $request->reason);

                return response()->json([
                    'success' => true,
                    'message' => "Payment status updated from {$oldStatus} to {$newStatus}",
                    'data' => [
                        'payment' => $payment->fresh()->load(['paymentMethod', 'paymentable']),
                        'status_change' => [
                            'old_status' => $oldStatus,
                            'new_status' => $newStatus,
                            'reason' => $request->reason
                        ]
                    ]
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pending payments that need attention.
     */
    public function getPendingPayments(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $query = Payment::where('business_id', $user->business_id)
                       ->where('status', Payment::STATUS_PENDING)
                       ->with(['paymentMethod', 'paymentable']);

        // Filter by payment method type (e.g., only cheques)
        if ($request->has('method_type')) {
            $query->whereHas('paymentMethod', function ($q) use ($request) {
                $q->where('type', $request->method_type);
            });
        }

        // Filter by date range
        if ($request->has('days_old')) {
            $daysOld = (int) $request->days_old;
            $query->where('transaction_date', '<=', now()->subDays($daysOld)->toDateString());
        }

        $pendingPayments = (clone $query)->orderBy('transaction_date', 'asc')
                                ->paginate($request->get('per_page', 15));

        // Clone the query for summary calculations to avoid query builder state issues
        $summaryQuery = clone $query;

        return response()->json([
            'success' => true,
            'data' => $pendingPayments,
            'summary' => [
                'total_pending_amount' => $summaryQuery->sum('amount'),
                'total_pending_count' => $summaryQuery->count(),
                'oldest_payment' => ($first = $summaryQuery->orderBy('transaction_date', 'asc')->first()) ? $first->transaction_date : null
            ]
        ]);
    }

    /**
     * Bulk update payment statuses.
     */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payment_ids' => 'required|array|min:1',
            'payment_ids.*' => 'required|integer|exists:payments,id',
            'status' => 'required|in:pending,clear,bounced,cancelled',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            return DB::transaction(function () use ($request) {
                $user = Auth::user();
                $paymentIds = $request->payment_ids;
                $newStatus = $request->status;
                $reason = $request->reason;

                $payments = Payment::where('business_id', $user->business_id)
                                 ->whereIn('id', $paymentIds)
                                 ->get();

                if ($payments->count() != count($paymentIds)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'One or more payments not found'
                    ], 404);
                }

                $updated = [];
                foreach ($payments as $payment) {
                    $oldStatus = $payment->status;
                    if ($oldStatus != $newStatus) {
                        $payment->updateStatus($newStatus, $reason);
                        $updated[] = [
                            'id' => $payment->id,
                            'old_status' => $oldStatus,
                            'new_status' => $newStatus
                        ];
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => "Updated " . count($updated) . " payment(s) to {$newStatus} status",
                    'data' => [
                        'updated_payments' => $updated,
                        'total_updated' => count($updated)
                    ]
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk update payment statuses',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
