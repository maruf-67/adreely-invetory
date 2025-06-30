<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    /**
     * Display a listing of payments for the business.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = Payment::where('business_id', $user->business_id)
            ->with(['paymentMethod']);

        // Filter by payment type
        if ($request->has('paymentable_type')) {
            $query->where('paymentable_type', $request->paymentable_type);
        }

        // Filter by payment method
        if ($request->has('payment_method_id')) {
            $query->where('payment_method_id', $request->payment_method_id);
        }

        // Date range filter
        if ($request->has('from_date')) {
            $query->whereDate('transaction_date', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('transaction_date', '<=', $request->to_date);
        }

        $payments = $query->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $payments
        ]);
    }

    /**
     * Store a newly created payment.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'paymentable_id' => 'required|integer',
            'paymentable_type' => 'required|in:App\Models\PurchaseOrder,App\Models\SalesOrder',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'details' => 'nullable|string',
            'status' => 'nullable|in:pending,clear,hold,rejected',
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

                // Validate payment method belongs to same business
                $paymentMethod = PaymentMethod::where('id', $request->payment_method_id)
                    ->where('business_id', $user->business_id)
                    ->first();

                if (!$paymentMethod) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid payment method selected'
                    ], 400);
                }

                // Get the order
                $orderModel = $request->paymentable_type;
                $order = $orderModel::where('id', $request->paymentable_id)
                    ->where('business_id', $user->business_id)
                    ->first();

                if (!$order) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Order not found'
                    ], 404);
                }

                // Check if payment amount doesn't exceed due amount
                $dueAmount = $order->total_amount - $order->paid_amount;
                if ($request->amount > $dueAmount) {
                    return response()->json([
                        'success' => false,
                        'message' => "Payment amount cannot exceed due amount of {$dueAmount}"
                    ], 400);
                }

                // Create payment
                $payment = Payment::create([
                    'business_id' => $user->business_id,
                    'paymentable_id' => $request->paymentable_id,
                    'paymentable_type' => $request->paymentable_type,
                    'payment_method_id' => $request->payment_method_id,
                    'amount' => $request->amount,
                    'transaction_date' => $request->transaction_date,
                    'details' => $request->details,
                    'status' => $request->status ?? 'clear',
                    'created_by' => $user->id,
                ]);

                // Update order paid amount if payment is not pending
                if ($payment->status !== 'pending') {
                    $order->increment('paid_amount', $request->amount);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Payment recorded successfully',
                    'data' => $payment->load('paymentMethod')
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified payment.
     */
    public function show($id): JsonResponse
    {
        $user = Auth::user();
        $payment = Payment::where('business_id', $user->business_id)
            ->with(['paymentMethod', 'creator'])
            ->find($id);

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $payment
        ]);
    }

    /**
     * Update payment status.
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,clear,hold,rejected',
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

                // Get the related order
                $orderModel = $payment->paymentable_type;
                $order = $orderModel::find($payment->paymentable_id);

                // Handle status transitions
                if ($oldStatus === 'pending' && $newStatus === 'clear') {
                    // Payment confirmed - add to order paid amount
                    $order->increment('paid_amount', $payment->amount);
                } elseif ($oldStatus === 'clear' && $newStatus === 'pending') {
                    // Payment reverted - subtract from order paid amount
                    $order->decrement('paid_amount', $payment->amount);
                } elseif ($oldStatus === 'clear' && $newStatus === 'rejected') {
                    // Payment rejected - subtract from order paid amount
                    $order->decrement('paid_amount', $payment->amount);
                } elseif ($oldStatus === 'pending' && $newStatus === 'rejected') {
                    // Pending payment rejected - no change to order amount
                }

                $payment->update(['status' => $newStatus]);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment status updated successfully',
                    'data' => $payment
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
     * Get payments for a specific order.
     */
    public function getOrderPayments($orderType, $orderId): JsonResponse
    {
        $user = Auth::user();
        
        $allowedTypes = ['purchase-orders', 'sales-orders'];
        if (!in_array($orderType, $allowedTypes)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid order type'
            ], 400);
        }

        $paymentableType = $orderType === 'purchase-orders' 
            ? 'App\Models\PurchaseOrder' 
            : 'App\Models\SalesOrder';

        $payments = Payment::where('business_id', $user->business_id)
            ->where('paymentable_type', $paymentableType)
            ->where('paymentable_id', $orderId)
            ->with(['paymentMethod', 'creator'])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $payments
        ]);
    }

    /**
     * Get payment analytics.
     */
    public function analytics(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $query = Payment::where('business_id', $user->business_id)
            ->where('status', 'clear');

        // Date range filter
        if ($request->has('from_date')) {
            $query->whereDate('transaction_date', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('transaction_date', '<=', $request->to_date);
        }

        $totalReceived = $query->where('paymentable_type', 'App\Models\SalesOrder')->sum('amount');
        $totalPaid = $query->where('paymentable_type', 'App\Models\PurchaseOrder')->sum('amount');

        $paymentMethodBreakdown = $query->join('payment_methods', 'payments.payment_method_id', '=', 'payment_methods.id')
            ->selectRaw('payment_methods.gateway_name, SUM(payments.amount) as total')
            ->groupBy('payment_methods.gateway_name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_received' => $totalReceived,
                'total_paid' => $totalPaid,
                'net_cash_flow' => $totalReceived - $totalPaid,
                'payment_method_breakdown' => $paymentMethodBreakdown
            ]
        ]);
    }
}
